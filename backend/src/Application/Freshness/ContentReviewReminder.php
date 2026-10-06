<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Freshness;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailSettings;
use Paxofi\CorporateWebsite\Application\Mail\Outbox;

/**
 * A weekly email to administrators listing content that is overdue for review
 * or due within two weeks (D-025). Called by bin/send-mail.php every few
 * minutes; it sends at most once in 7 days, and only when something is due.
 */
final class ContentReviewReminder
{
    public const INTERVAL_DAYS = 7;
    public const AHEAD_DAYS = 14;

    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly ContentReviews $reviews,
        private readonly ContentReviewStore $store,
        private readonly Outbox $outbox,
        private readonly AuditRecorder $audit,
        private readonly MailSettings $mail,
        private readonly Closure $clock,
    ) {
    }

    /** @return int items in the reminder, or 0 when none was sent */
    public function run(): int
    {
        if (!$this->mail->enabled) {
            return 0;
        }
        $now = ($this->clock)()->setTimezone(new DateTimeZone('UTC'));
        $last = $this->store->lastReminderAt();
        if ($last !== null && new DateTimeImmutable($last, new DateTimeZone('UTC')) > $now->modify('-' . self::INTERVAL_DAYS . ' days')) {
            return 0;
        }
        $limit = $this->reviews->today()->modify('+' . self::AHEAD_DAYS . ' days')->format('Y-m-d');
        $due = array_values(array_filter(
            $this->reviews->list(),
            static fn (array $item): bool => $item['review_by'] !== null && $item['review_by'] <= $limit,
        ));
        $recipients = $this->store->administratorEmails();
        if ($due === [] || $recipients === []) {
            return 0;
        }

        $overdue = count(array_filter($due, static fn (array $item): bool => $item['status'] === 'overdue'));
        $lines = ['These items on the website are due for review:', ''];
        foreach ($due as $item) {
            $lines[] = sprintf('- %s: %s (%s %s)', $item['type_label'], $item['title'], $item['status'] === 'overdue' ? 'overdue since' : 'review by', self::day($item['review_by']));
        }
        $lines[] = '';
        $lines[] = 'Check each one is still correct, then mark it reviewed:';
        $lines[] = $this->mail->staffLink('/admin/content?tab=reviews');
        $lines[] = '';
        $lines[] = 'You get this email at most once a week while something is due (runbook RB-24).';
        $subject = sprintf('%d website item%s due for review', count($due), count($due) === 1 ? '' : 's') . ($overdue > 0 ? " ({$overdue} overdue)" : '');

        $this->outbox->add(new Email($recipients, $subject, implode("\n", $lines) . "\n", kind: 'content_review_reminder'));
        $this->audit->record(new AuditEvent('content_review.reminder_sent', AuditEvent::OUTCOME_SUCCESS));

        return count($due);
    }

    private static function day(string $date): string
    {
        return (new DateTimeImmutable($date))->format('j M Y');
    }
}
