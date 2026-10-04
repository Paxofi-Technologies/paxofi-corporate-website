<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\RateLimited;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailSettings;
use Paxofi\CorporateWebsite\Application\Mail\Outbox;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * "Forgot password?" for staff (decision D-016).
 *
 * - The answer is the same whether or not the email belongs to an account,
 *   so the form cannot be used to find staff addresses.
 * - The emailed link holds a random 256-bit token; only its SHA-256 is
 *   stored. It expires after 30 minutes and works once.
 * - Setting a new password signs the person out everywhere. Two-factor
 *   sign-in (D-010) still applies the next time they sign in.
 * - At most 3 requests per account and 10 per IP address an hour.
 */
final class PasswordResetService
{
    public const LINK_MINUTES = 30;
    private const WINDOW_MINUTES = 60;
    private const MAX_PER_USER = 3;
    private const MAX_PER_IP = 10;
    public const INVALID_LINK = 'This reset link has expired or was already used. Ask for a new one.';

    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly StaffRepository $staff,
        private readonly PasswordResets $resets,
        private readonly SessionStore $sessions,
        private readonly PasswordHashing $hasher,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Outbox $outbox,
        private readonly MailSettings $mail,
        private readonly Closure $clock,
    ) {
    }

    public function available(): bool
    {
        return $this->mail->enabled;
    }

    public function request(array $input, RequestContext $context): void
    {
        $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
        if ($email === '' || !Email::isAddress($email)) {
            throw new ValidationFailed(['email' => 'Enter the email address you sign in with.'], 'Please correct the highlighted fields.');
        }
        $now = ($this->clock)();
        $user = $this->staff->findCredentialsByEmail($email)?->user;
        $recent = $this->resets->recentRequests($user?->id, $context->clientIp, $now, self::WINDOW_MINUTES);
        if ($recent['ip'] >= self::MAX_PER_IP) {
            throw new RateLimited(self::WINDOW_MINUTES * 60);
        }
        if ($user === null || !$user->isActive() || !$this->mail->enabled || $recent['user'] >= self::MAX_PER_USER) {
            // Same answer as success; recorded for the audit log only.
            $this->audit->record(new AuditEvent('staff.password_reset.requested', AuditEvent::OUTCOME_DENIED, 'user', $user?->id, requestId: $context->requestId));

            return;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = $now->modify('+' . self::LINK_MINUTES . ' minutes');
        $this->transactions->transaction(function () use ($token, $user, $now, $expires, $context): void {
            $this->resets->create(hash('sha256', $token), $user->id, $context->clientIp, $now, $expires);
            $this->outbox->add(new Email([$user->email], 'Reset your Paxofi staff password', implode("\n", [
                'Hello ' . Email::oneLine($user->displayName) . ',',
                '',
                'Someone (hopefully you) asked to reset the password for your Paxofi staff account.',
                'Open this link within ' . self::LINK_MINUTES . ' minutes to choose a new password. It works once:',
                '',
                $this->mail->staffLink('/admin/reset-password#' . $token),
                '',
                'If you did not ask for this, ignore this email: your password stays the same. If it keeps happening, tell an administrator.',
            ]) . "\n", kind: 'password_reset'));
            $this->audit->record(new AuditEvent('staff.password_reset.requested', AuditEvent::OUTCOME_SUCCESS, 'user', $user->id, requestId: $context->requestId));
        });
    }

    public function complete(array $input, RequestContext $context): void
    {
        $token = is_string($input['token'] ?? null) ? trim($input['token']) : '';
        $hash = hash('sha256', $token);
        $now = ($this->clock)();
        $reset = $token === '' || strlen($token) > 100 ? null : $this->resets->find($hash);
        $user = $reset === null ? null : $this->staff->findById($reset['user_id']);
        if ($reset === null || $reset['used'] || $reset['expires_at'] <= $now || $user === null || !$user->isActive()) {
            $this->audit->record(new AuditEvent('staff.password_reset.completed', AuditEvent::OUTCOME_DENIED, 'user', $user?->id, requestId: $context->requestId));
            throw new ValidationFailed(['token' => self::INVALID_LINK], self::INVALID_LINK);
        }

        $errors = [];
        $password = StaffInput::password($input, $user->email, $errors);
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        $this->transactions->transaction(function () use ($hash, $user, $password, $now, $context): void {
            if (!$this->resets->markUsed($hash, $now)) {
                throw new ValidationFailed(['token' => self::INVALID_LINK], self::INVALID_LINK);
            }
            $this->staff->updatePassword($user->id, $this->hasher->hash($password));
            $this->resets->deleteUnusedForUser($user->id);
            $this->sessions->revokeAllForUser($user->id);
            $this->outbox->add(new Email([$user->email], 'Your Paxofi staff password was changed', implode("\n", [
                'Hello ' . Email::oneLine($user->displayName) . ',',
                '',
                'The password for your Paxofi staff account was just changed with a reset link, and every signed-in session was signed out.',
                'If this was not you, tell an administrator straight away.',
            ]) . "\n", kind: 'password_changed'));
            $this->audit->record(new AuditEvent('staff.password_reset.completed', AuditEvent::OUTCOME_SUCCESS, 'user', $user->id, $user->id, $context->requestId));
        });
    }
}
