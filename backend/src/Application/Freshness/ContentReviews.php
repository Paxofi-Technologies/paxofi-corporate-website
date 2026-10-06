<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Freshness;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Admin\Pages\PageCopySchema;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * Review dates for everything on the website (D-025, SRS 14.19): page text,
 * products, services, industries, articles and Resources documents. Staff
 * mark an item reviewed (the next date moves 3, 6 or 12 months on) or set a
 * date themselves. Dates are Lagos calendar days.
 */
final class ContentReviews
{
    public const TYPES = [
        'page' => 'Page text',
        'product' => 'Product',
        'service' => 'Service',
        'industry' => 'Industry',
        'article' => 'Article',
        'resource' => 'Resource',
    ];
    public const INTERVALS = [3, 6, 12];
    /** An item is "due soon" this many days before its date. */
    public const DUE_SOON_DAYS = 30;
    private const STATUS_ORDER = ['overdue' => 0, 'due' => 1, 'none' => 2, 'ok' => 3];

    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly ContentReviewStore $store,
        private readonly PageCopySchema $pages,
        private readonly AuditRecorder $audit,
        private readonly Closure $clock,
    ) {
    }

    /**
     * Every live item with its review state, overdue first.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $today = $this->today();
        $reviews = $this->store->reviews();
        $items = [];
        foreach ($this->inventory() as $item) {
            $review = $reviews[$item['type'] . ':' . $item['key']] ?? null;
            $reviewBy = $review['review_by'] ?? null;
            $items[] = $item + [
                'type_label' => self::TYPES[$item['type']],
                'review_by' => $reviewBy,
                'last_reviewed_at' => $review['last_reviewed_at'] ?? null,
                'last_reviewed_by' => $review['last_reviewed_by'] ?? null,
                'note' => $review['note'] ?? null,
                'status' => self::status($reviewBy, $today),
            ];
        }
        usort($items, static fn (array $a, array $b): int => [self::STATUS_ORDER[$a['status']], $a['review_by'] ?? '9999', $a['type_label'], $a['title']]
            <=> [self::STATUS_ORDER[$b['status']], $b['review_by'] ?? '9999', $b['type_label'], $b['title']]);

        return $items;
    }

    /** @return array{overdue: int, due: int, none: int, ok: int} */
    public static function counts(array $items): array
    {
        $counts = ['overdue' => 0, 'due' => 0, 'none' => 0, 'ok' => 0];
        foreach ($items as $item) {
            $counts[$item['status']]++;
        }

        return $counts;
    }

    /**
     * {"action": "reviewed", "months": 3|6|12, "note"?} or {"review_by": "YYYY-MM-DD"|null, "note"?}.
     *
     * @return array<string, mixed> the item as list() shows it
     */
    public function update(string $type, string $key, mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $item = $this->item($type, $key);
        if (!is_array($input)) {
            throw new ValidationFailed([], 'Send the review as a JSON object.');
        }
        $note = self::note($input['note'] ?? null);
        $reviews = $this->store->reviews();
        $current = $reviews[$type . ':' . $key] ?? ['review_by' => null, 'last_reviewed_at' => null, 'last_reviewed_by_id' => null, 'last_reviewed_by' => null, 'note' => null];
        $today = $this->today();

        if (($input['action'] ?? null) === 'reviewed') {
            $months = $input['months'] ?? 6;
            if (!in_array($months, self::INTERVALS, true)) {
                throw new ValidationFailed(['months' => 'Choose 3, 6 or 12 months.'], 'Please correct the highlighted fields.');
            }
            $reviewBy = $today->modify("+{$months} months")->format('Y-m-d');
            $reviewedAt = ($this->clock)()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            $this->store->save($type, $key, $reviewBy, $reviewedAt, $staff->user->id, $note ?? $current['note']);
            $action = 'content_review.reviewed';
        } elseif (array_key_exists('review_by', $input)) {
            $reviewBy = self::date($input['review_by'], $today);
            $this->store->save($type, $key, $reviewBy, $current['last_reviewed_at'], $current['last_reviewed_by_id'], array_key_exists('note', $input) ? $note : $current['note']);
            $action = 'content_review.date_set';
        } else {
            throw new ValidationFailed([], 'Mark the item reviewed or set a review date.');
        }
        $this->audit->record(new AuditEvent($action, AuditEvent::OUTCOME_SUCCESS, $type, $key, $staff->user->id, $context->requestId));

        foreach ($this->list() as $row) {
            if ($row['type'] === $item['type'] && $row['key'] === $item['key']) {
                return $row;
            }
        }

        throw new ResourceNotFound('Item not found.');
    }

    /** Lagos calendar day. */
    public function today(): DateTimeImmutable
    {
        return new DateTimeImmutable(($this->clock)()->setTimezone(new DateTimeZone('Africa/Lagos'))->format('Y-m-d'), new DateTimeZone('Africa/Lagos'));
    }

    public static function status(?string $reviewBy, DateTimeImmutable $today): string
    {
        if ($reviewBy === null) {
            return 'none';
        }
        $date = $today->format('Y-m-d');
        if ($reviewBy < $date) {
            return 'overdue';
        }

        return $reviewBy <= $today->modify('+' . self::DUE_SOON_DAYS . ' days')->format('Y-m-d') ? 'due' : 'ok';
    }

    /** @return list<array{type: string, key: string, title: string, path: string|null, changed_at: string|null}> */
    private function inventory(): array
    {
        $items = [];
        $published = $this->store->pagesPublishedAt();
        foreach ($this->pages->pageKeys() as $page) {
            $definition = $this->pages->page($page);
            $items[] = [
                'type' => 'page',
                'key' => $page,
                'title' => $definition['label'],
                'path' => $page === 'site' ? null : $definition['path'],
                'changed_at' => $published[$page] ?? null,
            ];
        }
        foreach ($this->store->liveItems() as $item) {
            $slug = $item['slug'];
            $items[] = [
                'type' => $item['type'],
                'key' => $item['key'],
                'title' => $item['title'],
                'path' => match ($item['type']) {
                    'product' => '/products#' . $slug,
                    'service' => '/services#' . $slug,
                    'industry' => '/industries/' . $slug,
                    'article' => '/insights/' . $slug,
                    default => '/resources',
                },
                'changed_at' => $item['changed_at'],
            ];
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function item(string $type, string $key): array
    {
        if (isset(self::TYPES[$type])) {
            foreach ($this->inventory() as $item) {
                if ($item['type'] === $type && $item['key'] === $key) {
                    return $item;
                }
            }
        }

        throw new ResourceNotFound('That item is not on the website.');
    }

    private static function date(mixed $value, DateTimeImmutable $today): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Africa/Lagos'))
            : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new ValidationFailed(['review_by' => 'Enter a date like 2027-04-30.'], 'Please correct the highlighted fields.');
        }
        if ($value < $today->format('Y-m-d') || $value > $today->modify('+3 years')->format('Y-m-d')) {
            throw new ValidationFailed(['review_by' => 'Choose a date from today up to three years ahead.'], 'Please correct the highlighted fields.');
        }

        return $value;
    }

    private static function note(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new ValidationFailed(['note' => 'The note must be text.'], 'Please correct the highlighted fields.');
        }
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));
        if (mb_strlen($value) > 300) {
            throw new ValidationFailed(['note' => 'Keep the note to 300 characters.'], 'Please correct the highlighted fields.');
        }

        return $value === '' ? null : $value;
    }
}
