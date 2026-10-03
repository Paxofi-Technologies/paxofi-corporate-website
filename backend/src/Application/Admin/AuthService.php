<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\Forbidden;
use Paxofi\CorporateWebsite\Application\Exception\RateLimited;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\Unauthenticated;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * Staff sign-in, sessions and first-administrator setup (decision D-009).
 *
 * - Wrong email and wrong password are indistinguishable (same message, and an
 *   unknown email still pays the hashing cost).
 * - Sign-in is throttled per email and per IP address.
 * - Sessions expire after an absolute lifetime or a period without activity.
 */
final class AuthService
{
    public const FAILURE_WINDOW_MINUTES = 15;
    public const MAX_FAILURES_PER_EMAIL = 5;
    public const MAX_FAILURES_PER_IP = 20;
    private const TOUCH_INTERVAL_SECONDS = 60;
    private const SIGN_IN_FAILED = 'Email or password is incorrect.';

    private ?string $dummyHash = null;

    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly StaffRepository $staff,
        private readonly SessionStore $sessions,
        private readonly LoginAttempts $attempts,
        private readonly PasswordHashing $hasher,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $clock,
        private readonly ?string $setupToken = null,
        private readonly int $absoluteMinutes = 480,
        private readonly int $idleMinutes = 30,
    ) {
    }

    public function setupAvailable(): bool
    {
        return $this->setupToken !== null && strlen($this->setupToken) >= 32 && $this->staff->countAll() === 0;
    }

    /** Creates the first administrator, once, with the one-time setup token from the API configuration. */
    public function setup(array $input, RequestContext $context): SignIn
    {
        if ($this->setupToken === null || strlen($this->setupToken) < 32) {
            throw new ResourceNotFound();
        }
        $token = is_string($input['setup_token'] ?? null) ? $input['setup_token'] : '';
        if (!hash_equals($this->setupToken, $token)) {
            $this->audit->record(new AuditEvent('staff.setup', AuditEvent::OUTCOME_DENIED, requestId: $context->requestId));
            throw new Forbidden('The setup code is incorrect.');
        }
        if ($this->staff->countAll() > 0) {
            throw new Conflict('Setup has already been completed. Sign in instead.');
        }

        $errors = [];
        $email = StaffInput::email($input, $errors);
        $name = (string) StaffInput::displayName($input, $errors);
        $password = StaffInput::password($input, $email, $errors);
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        $id = $this->transactions->transaction(function () use ($email, $name, $password, $context): string {
            $id = $this->staff->create($email, $name, $this->hasher->hash($password), Role::Administrator);
            $this->audit->record(new AuditEvent('staff.setup', AuditEvent::OUTCOME_SUCCESS, 'user', $id, $id, $context->requestId));

            return $id;
        });

        return $this->startSession((string) $id, $context);
    }

    public function signIn(array $input, RequestContext $context): SignIn
    {
        $email = is_string($input['email'] ?? null) ? strtolower(trim($input['email'])) : '';
        $password = is_string($input['password'] ?? null) ? $input['password'] : '';
        if ($email === '' || $password === '' || mb_strlen($email) > 255 || strlen($password) > 1024) {
            throw new ValidationFailed([], 'Enter your email and password.');
        }

        $failures = $this->attempts->recentFailures($email, $context->clientIp, self::FAILURE_WINDOW_MINUTES);
        if ($failures['email'] >= self::MAX_FAILURES_PER_EMAIL || $failures['ip'] >= self::MAX_FAILURES_PER_IP) {
            $this->audit->record(new AuditEvent('staff.sign_in', AuditEvent::OUTCOME_DENIED, requestId: $context->requestId));
            throw new RateLimited(self::FAILURE_WINDOW_MINUTES * 60);
        }

        $credentials = $this->staff->findCredentialsByEmail($email);
        $hash = $credentials?->passwordHash ?? $this->dummyHash();
        $passwordMatches = $this->hasher->verify($password, $hash);
        $succeeded = $credentials !== null && $credentials->passwordHash !== null && $passwordMatches && $credentials->user->isActive();

        $this->attempts->record($email, $context->clientIp, $succeeded);
        if (!$succeeded) {
            $this->audit->record(new AuditEvent('staff.sign_in', AuditEvent::OUTCOME_FAILURE, 'user', $credentials?->user->id, requestId: $context->requestId));
            throw new Unauthenticated(self::SIGN_IN_FAILED);
        }

        $user = $credentials->user;
        if ($this->hasher->needsRehash((string) $credentials->passwordHash)) {
            $this->staff->updatePassword($user->id, $this->hasher->hash($password));
        }

        return $this->startSession($user->id, $context);
    }

    /** Resolves a session token to the signed-in staff member, or null when it is missing, expired or revoked. */
    public function authenticate(?string $token): ?AuthenticatedStaff
    {
        if ($token === null || !SessionToken::looksValid($token)) {
            return null;
        }
        $hash = SessionToken::hash($token);
        $session = $this->sessions->find($hash);
        $now = ($this->clock)();
        if ($session === null || $session->revoked || $now >= $session->expiresAt) {
            return null;
        }
        if ($now->getTimestamp() - $session->lastSeenAt->getTimestamp() > $this->idleMinutes * 60) {
            $this->sessions->revoke($hash);

            return null;
        }

        $user = $this->staff->findById($session->userId);
        if ($user === null || !$user->isActive()) {
            return null;
        }
        if ($now->getTimestamp() - $session->lastSeenAt->getTimestamp() >= self::TOUCH_INTERVAL_SECONDS) {
            $this->sessions->touch($hash, $now);
        }

        return new AuthenticatedStaff($user, $hash);
    }

    public function signOut(AuthenticatedStaff $staff, RequestContext $context): void
    {
        $this->sessions->revoke($staff->sessionHash);
        $this->audit->record(new AuditEvent('staff.sign_out', AuditEvent::OUTCOME_SUCCESS, 'user', $staff->user->id, $staff->user->id, $context->requestId));
    }

    /** Changes the caller's password and signs out their other sessions. */
    public function changePassword(AuthenticatedStaff $staff, array $input, RequestContext $context): void
    {
        $credentials = $this->staff->findCredentialsById($staff->user->id);
        $current = is_string($input['current_password'] ?? null) ? $input['current_password'] : '';
        $errors = [];
        if ($credentials?->passwordHash === null || !$this->hasher->verify($current, $credentials->passwordHash)) {
            $errors['current_password'] = 'Your current password is incorrect.';
        }
        $new = StaffInput::password($input, $staff->user->email, $errors, 'new_password');
        if (!isset($errors['new_password']) && $new === $current) {
            $errors['new_password'] = 'Choose a password you have not used here.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        $this->transactions->transaction(function () use ($staff, $new, $context): void {
            $this->staff->updatePassword($staff->user->id, $this->hasher->hash($new));
            $this->sessions->revokeAllForUser($staff->user->id, $staff->sessionHash);
            $this->audit->record(new AuditEvent('staff.password_changed', AuditEvent::OUTCOME_SUCCESS, 'user', $staff->user->id, $staff->user->id, $context->requestId));
        });
    }

    private function startSession(string $userId, RequestContext $context): SignIn
    {
        $now = ($this->clock)();
        $expiresAt = $now->modify("+{$this->absoluteMinutes} minutes");
        $token = SessionToken::generate();

        $this->transactions->transaction(function () use ($token, $userId, $now, $expiresAt, $context): void {
            $this->sessions->create(SessionToken::hash($token), $userId, $now, $expiresAt, $context);
            $this->staff->recordLogin($userId);
            $this->audit->record(new AuditEvent('staff.sign_in', AuditEvent::OUTCOME_SUCCESS, 'user', $userId, $userId, $context->requestId));
        });

        $user = $this->staff->findById($userId);
        if ($user === null) {
            throw new Unauthenticated();
        }

        return new SignIn($token, $user, $expiresAt);
    }

    private function dummyHash(): string
    {
        return $this->dummyHash ??= $this->hasher->hash('paxofi-timing-equaliser-' . bin2hex(random_bytes(8)));
    }
}
