<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\Logger;
use Throwable;

/**
 * Error alerts by email (D-016): server errors, database outages, failed
 * backups and emails that could not be sent. Collected during a request and
 * sent straight to the mail server afterwards (not through the outbox, which
 * needs the database), at most once an hour per kind of problem.
 */
final class ErrorAlerts
{
    public const QUIET_SECONDS = 3600;

    /** @var array<string, array{title: string, context: array<string, mixed>}> */
    private array $pending = [];

    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly MailSettings $settings,
        private readonly ?MailTransport $transport,
        private readonly AlertThrottle $throttle,
        private readonly Closure $clock,
        private readonly Logger $logger,
        private readonly string $source = 'API',
    ) {
    }

    public function enabled(): bool
    {
        return $this->settings->enabled && $this->transport !== null && $this->settings->errorAlertTo !== [];
    }

    /** @param array<string, mixed> $context */
    public function report(string $title, array $context = []): void
    {
        if (!$this->enabled()) {
            return;
        }
        $key = hash('sha256', $title . '|' . ($context['exception'] ?? '') . '|' . ($context['at'] ?? '') . '|' . ($context['kind'] ?? ''));
        $this->pending[$key] ??= ['title' => $title, 'context' => $context];
    }

    public function hasPending(): bool
    {
        return $this->pending !== [];
    }

    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];
        foreach ($pending as $fingerprint => $alert) {
            $now = ($this->clock)();
            if (!$this->throttle->allow($fingerprint, $now, self::QUIET_SECONDS)) {
                continue;
            }
            try {
                $this->transport?->send(new Email($this->settings->errorAlertTo, "[{$this->settings->siteName} {$this->source}] {$alert['title']}", $this->body($alert['title'], $alert['context'], $now), kind: 'error_alert'));
            } catch (MailFailed|Throwable $exception) {
                $this->logger->error('alert.send_failed', ['title' => $alert['title'], 'error' => $exception->getMessage()]);
            }
        }
    }

    /** @param array<string, mixed> $context */
    private function body(string $title, array $context, DateTimeImmutable $now): string
    {
        $lines = [$title, '', 'Time (UTC): ' . $now->format('Y-m-d H:i:s'), 'Source: ' . $this->source];
        foreach ($context as $key => $value) {
            $lines[] = ucfirst(str_replace('_', ' ', (string) $key)) . ': ' . Email::oneLine(is_scalar($value) ? (string) $value : (string) json_encode($value));
        }
        $lines[] = '';
        $lines[] = 'Further alerts about this same problem are paused for an hour. See docs/RUNBOOKS.md (RB-17) for what to check.';

        return implode("\n", $lines) . "\n";
    }
}
