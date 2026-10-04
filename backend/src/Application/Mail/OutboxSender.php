<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\Logger;

/**
 * Sends due emails from the outbox (D-016). A failure is retried after 1, 5,
 * 15, 60 and 240 minutes; after six attempts the email is marked failed and
 * an error alert is raised.
 */
final class OutboxSender
{
    private const RETRY_MINUTES = [1, 5, 15, 60, 240];

    /**
     * @param Closure(): DateTimeImmutable $clock
     * @param (Closure(string, array<string, mixed>): void)|null $onGiveUp
     */
    public function __construct(
        private readonly Outbox $outbox,
        private readonly MailTransport $transport,
        private readonly Closure $clock,
        private readonly Logger $logger,
        private readonly ?Closure $onGiveUp = null,
    ) {
    }

    /** @return array{sent: int, failed: int} */
    public function run(int $limit = 20, float $budgetSeconds = 50.0): array
    {
        $started = microtime(true);
        $sent = 0;
        $failed = 0;
        foreach ($this->outbox->due(($this->clock)(), $limit) as $item) {
            if (microtime(true) - $started > $budgetSeconds) {
                break;
            }
            try {
                $this->transport->send($item['email']);
                $this->outbox->markSent($item['id'], ($this->clock)());
                $sent++;
            } catch (MailFailed $exception) {
                $failed++;
                $attempt = $item['attempts'] + 1;
                $wait = self::RETRY_MINUTES[$attempt - 1] ?? null;
                $next = $wait === null ? null : ($this->clock)()->modify("+{$wait} minutes");
                $this->outbox->markFailed($item['id'], mb_substr($exception->getMessage(), 0, 500), $next);
                $this->logger->warning('mail.send_failed', ['email_id' => $item['id'], 'kind' => $item['email']->kind, 'attempt' => $attempt, 'error' => $exception->getMessage()]);
                if ($next === null && $this->onGiveUp !== null) {
                    ($this->onGiveUp)('Email could not be sent', ['kind' => $item['email']->kind, 'email_id' => $item['id'], 'error' => $exception->getMessage()]);
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }
}
