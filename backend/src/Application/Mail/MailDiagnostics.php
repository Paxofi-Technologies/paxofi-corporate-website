<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use Closure;
use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Admin\TwoFactor\SecretEncryption;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * The staff Email page (decision D-031): the email settings in force (from
 * backend/.env, or saved here, which wins), the state of the outbox, a test
 * email sent straight away, and a button that sends the queued emails now.
 * Every result shows the real error, so a problem can be found without server
 * logs. The mailbox password is stored encrypted and never shown.
 */
final class MailDiagnostics
{
    /**
     * @param Closure(): array{transport: ?MailTransport, values: array<string, string>, sources: array<string, string>, problem: ?string} $state
     * @param Closure(): void $forget makes the next $state() read the saved settings again
     * @param Closure(): array{sent: int, failed: int} $sendQueued
     * @param Closure(): DateTimeImmutable $clock
     */
    public function __construct(
        private readonly Closure $state,
        private readonly Closure $forget,
        private readonly MailSettingsStore $store,
        private readonly ?SecretEncryption $secrets,
        private readonly OutboxStats $stats,
        private readonly Closure $sendQueued,
        private readonly AuditRecorder $audit,
        private readonly Closure $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $state = ($this->state)();
        $values = $state['values'];
        $fields = [];
        foreach (MailSettingsStore::KEYS as $key) {
            $fields[$key] = [
                // Never the password itself.
                'value' => $key === 'password' ? null : ($values[$key] ?? ''),
                'set' => ($values[$key] ?? '') !== '',
                'source' => $state['sources'][$key] ?? 'none',
            ];
        }

        return [
            'configured' => $state['transport'] !== null,
            'fields' => $fields,
            'problem' => $state['problem'] ?? ($state['transport'] === null ? self::whyNotConfigured($values) : null),
            'can_store_password' => $this->secrets !== null,
            'queue' => $this->stats->summary(($this->clock)()),
            'checked_at' => ($this->clock)()->format(DATE_ATOM),
        ];
    }

    /**
     * Saves the settings sent. An empty value removes the saved one, so the
     * .env setting applies again. The password is only changed when a new one
     * is typed, or removed with clear_password.
     *
     * @return array<string, mixed>
     */
    public function save(mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $input = is_array($input) ? $input : [];
        $errors = [];
        $changes = [];
        $text = static fn (mixed $value): string => is_string($value) || is_int($value) ? trim((string) $value) : '';
        $rules = [
            'transport' => static fn (string $v): ?string => in_array($v, ['', 'smtp', 'mail'], true) ? null : 'Choose SMTP (recommended) or the server\'s mail function.',
            'host' => static fn (string $v): ?string => $v === '' || preg_match('/^[A-Za-z0-9.-]{1,253}$/', $v) === 1 ? null : 'Enter the outgoing mail server, for example mail.paxofi.com.',
            'port' => static fn (string $v): ?string => $v === '' || (ctype_digit($v) && (int) $v >= 1 && (int) $v <= 65535) ? null : 'Enter a port number, usually 465.',
            'encryption' => static fn (string $v): ?string => in_array($v, ['', 'ssl', 'tls', 'none'], true) ? null : 'Choose SSL, TLS or none.',
            'username' => static fn (string $v): ?string => mb_strlen($v) <= 255 ? null : 'The username can be up to 255 characters.',
            'from_address' => static fn (string $v): ?string => $v === '' || Email::isAddress($v) ? null : 'Enter the address emails are sent from, for example no-reply@paxofi.com.',
            'from_name' => static fn (string $v): ?string => mb_strlen($v) <= 100 && Email::oneLine($v) === $v ? null : 'The sender name can be up to 100 characters on one line.',
        ];
        foreach ($rules as $key => $rule) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $text($input[$key]);
            if (in_array($key, ['transport', 'encryption'], true)) {
                $value = strtolower($value);
            }
            $error = $rule($value);
            if ($error !== null) {
                $errors[$key] = $error;
                continue;
            }
            $changes[$key] = $value === '' ? null : $value;
        }
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        if ($password !== '') {
            if ($this->secrets === null) {
                $errors['password'] = 'Saving the password here needs MFA_ENCRYPTION_KEY in backend/.env (guide Step 10b). Until then, keep MAIL_PASSWORD in .env.';
            } elseif (strlen($password) > 500) {
                $errors['password'] = 'The password can be up to 500 characters.';
            } else {
                $changes['password'] = $this->secrets->encrypt($password);
            }
        } elseif (($input['clear_password'] ?? false) === true) {
            $changes['password'] = null;
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        foreach ($changes as $key => $value) {
            $this->store->set($key, $value, $staff->user->id);
        }
        $this->audit->record(new AuditEvent('mail.settings_updated', AuditEvent::OUTCOME_SUCCESS, 'mail', null, $staff->user->id, $context->requestId));
        ($this->forget)();

        return $this->status();
    }

    /** Sends one email straight to the signed-in administrator, not through the queue. */
    public function sendTest(AuthenticatedStaff $staff, RequestContext $context): array
    {
        $result = $this->attempt(function (MailTransport $transport) use ($staff): void {
            $transport->send(new Email(
                [$staff->user->email],
                'Test email from the Paxofi staff area',
                "This is a test email sent from the Email page of the Paxofi staff area.\n\nIf you can read it, the website can send email.\n",
                kind: 'mail_test',
            ));
        });
        $this->audit->record(new AuditEvent('mail.test_sent', $result['ok'] ? AuditEvent::OUTCOME_SUCCESS : AuditEvent::OUTCOME_FAILURE, 'mail', null, $staff->user->id, $context->requestId));

        return $result + ['to' => $staff->user->email, 'status' => $this->status()];
    }

    /** Sends what is due in the queue now, the same way the 5-minute cron job does. */
    public function sendQueued(AuthenticatedStaff $staff, RequestContext $context): array
    {
        $counts = ['sent' => 0, 'failed' => 0];
        $result = $this->attempt(function () use (&$counts): void {
            $counts = ($this->sendQueued)();
        });
        $this->audit->record(new AuditEvent('mail.queue_sent', $result['ok'] ? AuditEvent::OUTCOME_SUCCESS : AuditEvent::OUTCOME_FAILURE, 'mail', null, $staff->user->id, $context->requestId));

        return $result + $counts + ['status' => $this->status()];
    }

    /**
     * @param Closure(MailTransport): void $action
     * @return array{ok: bool, error: ?string, seconds: float}
     */
    private function attempt(Closure $action): array
    {
        $state = ($this->state)();
        $transport = $state['transport'];
        if ($transport === null) {
            return ['ok' => false, 'error' => $state['problem'] ?? self::whyNotConfigured($state['values']), 'seconds' => 0.0];
        }
        $started = microtime(true);
        try {
            $action($transport);
            $error = null;
        } catch (\Throwable $exception) {
            $error = ($exception instanceof MailFailed ? '' : (new \ReflectionClass($exception))->getShortName() . ': ') . $exception->getMessage();
        }

        return ['ok' => $error === null, 'error' => $error === null ? null : mb_substr($error, 0, 500), 'seconds' => round(microtime(true) - $started, 1)];
    }

    /** @param array<string, string> $values */
    private static function whyNotConfigured(array $values): string
    {
        $transport = $values['transport'] ?? '';
        if ($transport === '') {
            return 'Email sending is off: no transport is chosen. Choose SMTP below (or set MAIL_TRANSPORT in backend/.env).';
        }
        if (!in_array($transport, ['smtp', 'mail'], true)) {
            return "Email sending is off: the transport \"{$transport}\" is not known; choose SMTP.";
        }
        if (!Email::isAddress($values['from_address'] ?? '')) {
            return 'Email sending is off: the "send from" address is missing or not an email address.';
        }
        if ($transport === 'smtp' && ($values['host'] ?? '') === '') {
            return 'Email sending is off: the outgoing mail server (host) is missing.';
        }

        return 'Email sending is off: check the settings below.';
    }
}
