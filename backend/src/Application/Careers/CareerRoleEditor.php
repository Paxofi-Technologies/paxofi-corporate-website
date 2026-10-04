<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Closure;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * Roles on careers.paxofi.com, edited in the staff area (D-018). A role is a
 * draft (not shown), published (shown, accepting applications) or closed
 * (not shown; its applications stay). The address (slug) is set from the
 * title when the role is created and never changes, so shared links keep
 * working.
 */
final class CareerRoleEditor
{
    /** @param Closure(): string $uuid */
    public function __construct(
        private readonly CareerRoles $roles,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $uuid,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return array_map(static fn (CareerRole $role): array => $role->toAdminArray(), $this->roles->all());
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->find($id)->toAdminArray();
    }

    /** @return array<string, mixed> */
    public function create(mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $values = RoleInput::validate($input);
        $base = RoleInput::slug($values['title']);
        $slug = $base;
        for ($n = 2; $this->roles->bySlug($slug) !== null; $n++) {
            $slug = $base . '-' . $n;
        }
        $id = ($this->uuid)();
        $this->transactions->transaction(function () use ($id, $slug, $values, $staff, $context): void {
            $this->roles->create($id, $slug, $values);
            $this->audit->record(new AuditEvent('career_role.created', AuditEvent::OUTCOME_SUCCESS, 'career_role', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id);
    }

    /** @return array<string, mixed> */
    public function update(string $id, mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->find($id);
        $values = RoleInput::validate($input);
        $this->transactions->transaction(function () use ($id, $values, $staff, $context): void {
            $this->roles->update($id, $values);
            $this->audit->record(new AuditEvent('career_role.updated', AuditEvent::OUTCOME_SUCCESS, 'career_role', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id);
    }

    /** @return array<string, mixed> */
    public function setState(string $id, mixed $state, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->find($id);
        if (!in_array($state, [CareerRole::DRAFT, CareerRole::PUBLISHED, CareerRole::CLOSED], true)) {
            throw new ValidationFailed(['state' => 'Choose draft, published or closed.'], 'Please correct the highlighted fields.');
        }
        $this->transactions->transaction(function () use ($id, $state, $staff, $context): void {
            $this->roles->setState($id, $state);
            $this->audit->record(new AuditEvent('career_role.' . $state, AuditEvent::OUTCOME_SUCCESS, 'career_role', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id);
    }

    private function find(string $id): CareerRole
    {
        $role = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? $this->roles->find($id) : null;

        return $role ?? throw new ResourceNotFound('Role not found.');
    }
}
