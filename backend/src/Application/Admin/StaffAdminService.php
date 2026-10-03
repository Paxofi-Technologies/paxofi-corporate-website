<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;

/** Staff account management for administrators. */
final class StaffAdminService
{
    public function __construct(
        private readonly StaffRepository $staff,
        private readonly SessionStore $sessions,
        private readonly PasswordHashing $hasher,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return array_map(static fn (StaffUser $user): array => $user->toArray(), $this->staff->all());
    }

    /** Creates an account with a temporary password the administrator passes on securely. */
    public function create(array $input, AuthenticatedStaff $actor, RequestContext $context): array
    {
        $errors = [];
        $email = StaffInput::email($input, $errors);
        $name = (string) StaffInput::displayName($input, $errors);
        $role = StaffInput::role($input, $errors);
        $password = StaffInput::password($input, $email, $errors);
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        if ($this->staff->findCredentialsByEmail($email) !== null) {
            throw new Conflict('An account with this email already exists.');
        }

        $id = $this->transactions->transaction(function () use ($email, $name, $role, $password, $actor, $context): string {
            $id = $this->staff->create($email, $name, $this->hasher->hash($password), $role);
            $this->audit->record(new AuditEvent('staff.created', AuditEvent::OUTCOME_SUCCESS, 'user', $id, $actor->user->id, $context->requestId));

            return $id;
        });

        return ($this->staff->findById((string) $id) ?? throw new ResourceNotFound())->toArray();
    }

    /** Changes name, role or status, or sets a new temporary password. */
    public function update(string $id, array $input, AuthenticatedStaff $actor, RequestContext $context): array
    {
        $target = $this->staff->findById($id) ?? throw new ResourceNotFound('Account not found.');
        $errors = [];
        $name = StaffInput::displayName($input, $errors, required: false);
        $role = StaffInput::role($input, $errors, required: false);
        $status = null;
        if (array_key_exists('status', $input)) {
            $status = in_array($input['status'], [StaffUser::STATUS_ACTIVE, StaffUser::STATUS_DISABLED], true) ? $input['status'] : null;
            if ($status === null) {
                $errors['status'] = 'Choose active or disabled.';
            }
        }
        $password = null;
        if (array_key_exists('password', $input)) {
            $password = StaffInput::password($input, $target->email, $errors);
        }

        $isSelf = $target->id === $actor->user->id;
        if ($isSelf && $status === StaffUser::STATUS_DISABLED) {
            $errors['status'] = 'You cannot disable your own account.';
        }
        if ($isSelf && $role !== null && $role !== Role::Administrator) {
            $errors['role'] = 'You cannot remove your own administrator role.';
        }
        if ($isSelf && $password !== null) {
            $errors['password'] = 'Change your own password from your account page.';
        }
        $losesAdmin = $target->role === Role::Administrator && $target->isActive()
            && (($role !== null && $role !== Role::Administrator) || $status === StaffUser::STATUS_DISABLED);
        if ($losesAdmin && $this->staff->countActiveAdministrators() <= 1) {
            $errors['role'] = 'At least one active administrator is required.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        $this->transactions->transaction(function () use ($target, $name, $status, $role, $password, $actor, $context): void {
            $this->staff->update($target->id, $name, $status, $role);
            if ($password !== null) {
                $this->staff->updatePassword($target->id, $this->hasher->hash($password));
            }
            if ($status === StaffUser::STATUS_DISABLED || $password !== null || ($role !== null && $role !== $target->role)) {
                $this->sessions->revokeAllForUser($target->id);
            }
            $this->audit->record(new AuditEvent('staff.updated', AuditEvent::OUTCOME_SUCCESS, 'user', $target->id, $actor->user->id, $context->requestId));
        });

        return ($this->staff->findById($target->id) ?? throw new ResourceNotFound())->toArray();
    }
}
