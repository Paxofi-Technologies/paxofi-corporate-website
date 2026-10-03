<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\TwoFactor;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Admin\PasswordHashing;
use Paxofi\CorporateWebsite\Application\Admin\Role;
use Paxofi\CorporateWebsite\Application\Admin\SessionStore;
use Paxofi\CorporateWebsite\Application\Admin\StaffRepository;
use Paxofi\CorporateWebsite\Application\Admin\StaffUser;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\Exception\Forbidden;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * Two-factor sign-in with an authenticator app (decision D-010).
 *
 * - Administrators must use it once the server has MFA_ENCRYPTION_KEY; it is
 *   optional for other roles.
 * - Secrets are encrypted at rest; each code works once; recovery codes are
 *   single-use and stored hashed.
 * - Recovery codes keep working without the encryption key, so a lost or
 *   changed key never locks staff out for good.
 */
final class TwoFactorService
{
    public const INVALID_CODE = 'That code is not valid. Enter the current 6-digit code from your authenticator app, or a recovery code.';

    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly TwoFactorStore $store,
        private readonly ?SecretEncryption $encryption,
        private readonly StaffRepository $staff,
        private readonly SessionStore $sessions,
        private readonly PasswordHashing $hasher,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $clock,
    ) {
    }

    /** True when the server can store authenticator secrets (MFA_ENCRYPTION_KEY is set). */
    public function configured(): bool
    {
        return $this->encryption !== null;
    }

    public function requiredFor(StaffUser $user): bool
    {
        return $this->configured() && $user->role === Role::Administrator;
    }

    /** @return array{configured: bool, enabled: bool, required: bool, recovery_codes_left: int} */
    public function status(AuthenticatedStaff $staff): array
    {
        $state = $this->store->find($staff->user->id);

        return [
            'configured' => $this->configured(),
            'enabled' => $state->enabled(),
            'required' => $this->requiredFor($staff->user),
            'recovery_codes_left' => $state->recoveryCodesLeft,
        ];
    }

    /**
     * Starts set-up: creates a new secret, kept as pending until a code from it is confirmed.
     *
     * @return array{secret: string, otpauth_uri: string}
     */
    public function beginSetup(AuthenticatedStaff $staff): array
    {
        $encryption = $this->requireEncryption();
        if ($this->store->find($staff->user->id)->enabled()) {
            throw new Conflict('Two-factor sign-in is already on. Turn it off first to move to a new device.');
        }
        $secret = Totp::generateSecret();
        $this->store->setPending($staff->user->id, $encryption->encrypt($secret));

        return ['secret' => Base32::encode($secret), 'otpauth_uri' => Totp::provisioningUri($secret, $staff->user->email)];
    }

    /**
     * Confirms set-up with a code from the app, turns two-factor on, signs out other
     * sessions and returns the recovery codes (shown once).
     *
     * @return list<string>
     */
    public function enable(AuthenticatedStaff $staff, array $input, RequestContext $context): array
    {
        $encryption = $this->requireEncryption();
        $state = $this->store->find($staff->user->id);
        if ($state->enabled()) {
            throw new Conflict('Two-factor sign-in is already on.');
        }
        $secret = $state->encryptedPendingSecret === null ? null : $encryption->decrypt($state->encryptedPendingSecret);
        if ($secret === null) {
            throw new ValidationFailed(['code' => 'Start the set-up again.'], 'The set-up has expired. Start again.');
        }
        $step = Totp::verify($secret, self::codeFrom($input), ($this->clock)());
        if ($step === null) {
            throw new ValidationFailed(['code' => 'That code does not match. Check the time on your phone and try the current code.'], 'Please correct the highlighted fields.');
        }

        $codes = RecoveryCodes::generate();
        $this->transactions->transaction(function () use ($staff, $encryption, $secret, $step, $codes, $context): void {
            $this->store->enable($staff->user->id, $encryption->encrypt($secret), $step, array_map(RecoveryCodes::hash(...), $codes));
            $this->sessions->revokeAllForUser($staff->user->id, $staff->sessionHash);
            $this->audit->record(new AuditEvent('staff.two_factor.enabled', AuditEvent::OUTCOME_SUCCESS, 'user', $staff->user->id, $staff->user->id, $context->requestId));
        });

        return $codes;
    }

    /**
     * Checks a second-factor code for sign-in: an authenticator code (once only)
     * or an unused recovery code. Returns 'totp', 'recovery' or null.
     */
    public function verify(string $userId, string $code): ?string
    {
        $state = $this->store->find($userId);
        if (!$state->enabled()) {
            return null;
        }
        if (RecoveryCodes::looksLike($code)) {
            return $this->store->useRecoveryCode($userId, RecoveryCodes::hash($code)) ? 'recovery' : null;
        }
        $secret = $this->encryption?->decrypt((string) $state->encryptedSecret);
        if ($secret === null) {
            return null;
        }
        $step = Totp::verify($secret, $code, ($this->clock)(), $state->lastUsedStep);

        return $step !== null && $this->store->useStep($userId, $step) ? 'totp' : null;
    }

    /**
     * Replaces the recovery codes; needs a current authenticator code.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(AuthenticatedStaff $staff, array $input, RequestContext $context): array
    {
        $state = $this->store->find($staff->user->id);
        if (!$state->enabled()) {
            throw new Conflict('Turn on two-factor sign-in first.');
        }
        $code = self::codeFrom($input);
        if (RecoveryCodes::looksLike($code) || $this->verify($staff->user->id, $code) !== 'totp') {
            throw new ValidationFailed(['code' => 'Enter the current 6-digit code from your authenticator app.'], 'Please correct the highlighted fields.');
        }
        $codes = RecoveryCodes::generate();
        $this->transactions->transaction(function () use ($staff, $codes, $context): void {
            $this->store->replaceRecoveryCodes($staff->user->id, array_map(RecoveryCodes::hash(...), $codes));
            $this->audit->record(new AuditEvent('staff.two_factor.recovery_codes_replaced', AuditEvent::OUTCOME_SUCCESS, 'user', $staff->user->id, $staff->user->id, $context->requestId));
        });

        return $codes;
    }

    /** Turns two-factor off for the caller, after their password; not allowed where the role requires it. */
    public function disable(AuthenticatedStaff $staff, array $input, RequestContext $context): void
    {
        if ($this->requiredFor($staff->user)) {
            throw new Forbidden('Administrators must keep two-factor sign-in on. To move to a new phone, ask another administrator to reset it.');
        }
        if (!$this->store->find($staff->user->id)->enabled()) {
            throw new Conflict('Two-factor sign-in is already off.');
        }
        $credentials = $this->staff->findCredentialsById($staff->user->id);
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        if ($credentials?->passwordHash === null || !$this->hasher->verify($password, $credentials->passwordHash)) {
            throw new ValidationFailed(['password' => 'Your password is incorrect.'], 'Please correct the highlighted fields.');
        }
        $this->transactions->transaction(function () use ($staff, $context): void {
            $this->store->disable($staff->user->id);
            $this->audit->record(new AuditEvent('staff.two_factor.disabled', AuditEvent::OUTCOME_SUCCESS, 'user', $staff->user->id, $staff->user->id, $context->requestId));
        });
    }

    /** An administrator removes another person's two-factor (lost phone); that person is signed out everywhere. */
    public function reset(string $targetId, AuthenticatedStaff $actor, RequestContext $context): StaffUser
    {
        if ($targetId === $actor->user->id) {
            throw new Forbidden('You cannot reset your own two-factor sign-in. Ask another administrator, or use a recovery code.');
        }
        $target = $this->staff->findById($targetId) ?? throw new ResourceNotFound('Account not found.');
        $this->transactions->transaction(function () use ($target, $actor, $context): void {
            $this->store->disable($target->id);
            $this->sessions->revokeAllForUser($target->id);
            $this->audit->record(new AuditEvent('staff.two_factor.reset', AuditEvent::OUTCOME_SUCCESS, 'user', $target->id, $actor->user->id, $context->requestId));
        });

        return $this->staff->findById($targetId) ?? $target;
    }

    private function requireEncryption(): SecretEncryption
    {
        return $this->encryption ?? throw new DependencyUnavailable('Two-factor sign-in is not set up on the server yet (MFA_ENCRYPTION_KEY).');
    }

    private static function codeFrom(array $input): string
    {
        $code = is_string($input['code'] ?? null) ? trim($input['code']) : '';

        return mb_substr($code, 0, 64);
    }
}
