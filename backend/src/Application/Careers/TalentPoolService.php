<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/**
 * The talent pool (decision D-029): people who were not selected, or who
 * declined or withdrew, and who agreed to be contacted about future
 * programmes. Consent is recorded by staff (when, and how it was given), lasts
 * 24 months and then lapses; the entry is deleted when consent is withdrawn,
 * when it lapses (daily retention job) or when the application is erased.
 * The entry copies only what is needed to get in touch, never the CV.
 */
final class TalentPoolService
{
    /** @var list<ApplicationStage> */
    public const ELIGIBLE_STAGES = [ApplicationStage::Rejected, ApplicationStage::Declined, ApplicationStage::Withdrawn];
    public const CONSENT_MONTHS = 24;
    /** @var array<string, string> */
    public const METHODS = [
        'email_reply' => 'The candidate replied to our email',
        'written' => 'Signed or written consent',
        'other' => 'Another way (explain in the note)',
    ];

    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly TalentPoolStore $pool,
        private readonly JobApplications $applications,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $clock,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function forApplication(string $applicationId): ?array
    {
        $entry = $this->pool->forApplication($applicationId);

        return $entry === null ? null : ['id' => $entry['id'], 'consent_given_on' => $entry['consent_given_on'], 'consent_expires_on' => $entry['consent_expires_on']];
    }

    public function deleteForApplication(string $applicationId): void
    {
        $this->pool->deleteForApplication($applicationId);
    }

    /** @param array<string, mixed> $query */
    public function list(array $query): array
    {
        $filters = [];
        if (is_string($query['engagement'] ?? null) && EngagementType::tryFrom($query['engagement']) !== null) {
            $filters['engagement'] = $query['engagement'];
        }
        if (is_string($query['q'] ?? null) && trim($query['q']) !== '') {
            $filters['q'] = mb_substr(trim($query['q']), 0, 100);
        }
        $page = max(1, (int) ($query['page'] ?? 1));
        $result = $this->pool->search($filters, $page, 25);
        $soon = ($this->clock)()->setTimezone(new DateTimeZone('Africa/Lagos'))->modify('+60 days')->format('Y-m-d');
        $items = array_map(static function (array $row) use ($soon): array {
            $type = EngagementType::tryFrom((string) $row['engagement_type']) ?? EngagementType::Fellow;

            return [
                'id' => $row['id'], 'application_id' => $row['application_id'], 'reference' => $row['reference'],
                'full_name' => $row['full_name'], 'email' => $row['email'], 'location' => $row['location'],
                'role_title' => $row['role_title'], 'engagement_type' => $type->value, 'engagement_label' => $type->label(),
                'portfolio_url' => $row['portfolio_url'], 'linkedin_url' => $row['linkedin_url'], 'note' => $row['note'],
                'consent_given_on' => $row['consent_given_on'], 'consent_method' => $row['consent_method'],
                'consent_method_label' => self::METHODS[$row['consent_method']] ?? $row['consent_method'],
                'consent_expires_on' => $row['consent_expires_on'], 'expiring_soon' => $row['consent_expires_on'] <= $soon,
                'added_on' => substr((string) $row['created_at'], 0, 10),
            ];
        }, $result['items']);

        return [
            'items' => $items,
            'meta' => ['page' => $page, 'per_page' => 25, 'total' => $result['total'], 'total_pages' => max(1, (int) ceil($result['total'] / 25)), 'consent_months' => self::CONSENT_MONTHS, 'methods' => self::METHODS],
        ];
    }

    /**
     * Adds the candidate of an application the viewer may see.
     *
     * @param array<string, mixed> $application an application row
     * @return array<string, mixed>
     */
    public function add(array $application, mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $input = is_array($input) ? $input : [];
        $stage = ApplicationStage::from((string) $application['stage']);
        if (!in_array($stage, self::ELIGIBLE_STAGES, true)) {
            throw new Conflict('Only candidates who were not selected, declined or withdrew can join the talent pool.');
        }
        $today = ($this->clock)()->setTimezone(new DateTimeZone('Africa/Lagos'));
        $errors = [];
        $given = self::date($input['consent_given_on'] ?? null);
        if ($given === null || $given > $today->format('Y-m-d') || $given < substr((string) $application['created_at'], 0, 10)) {
            $errors['consent_given_on'] = 'Enter the day the candidate agreed (not before they applied, not in the future), for example 2026-11-20.';
        }
        $method = is_string($input['consent_method'] ?? null) && isset(self::METHODS[$input['consent_method']]) ? $input['consent_method'] : null;
        if ($method === null) {
            $errors['consent_method'] = 'Say how the candidate agreed.';
        }
        $note = is_string($input['note'] ?? null) ? trim(mb_scrub($input['note'], 'UTF-8')) : '';
        if (mb_strlen($note) > 500 || ($method === 'other' && mb_strlen($note) < 10)) {
            $errors['note'] = $method === 'other' ? 'Explain how the candidate agreed (10 to 500 characters).' : 'The note can be up to 500 characters.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        $email = strtolower(trim((string) $application['email']));
        if ($this->pool->findByEmail($email) !== null) {
            throw new Conflict('This person is already in the talent pool.');
        }
        $id = Uuid::v4();
        $expires = (new DateTimeImmutable($given, new DateTimeZone('Africa/Lagos')))->modify('+' . self::CONSENT_MONTHS . ' months')->format('Y-m-d');
        $this->transactions->transaction(function () use ($id, $application, $email, $given, $method, $note, $expires, $staff, $context): void {
            $this->pool->add([
                'id' => $id, 'application_id' => $application['id'], 'reference' => $application['reference'], 'full_name' => $application['full_name'],
                'email' => $email, 'location' => $application['location'], 'role_title' => $application['role_title'],
                'engagement_type' => $application['engagement_type'] ?? 'fellow', 'portfolio_url' => $application['portfolio_url'], 'linkedin_url' => $application['linkedin_url'],
                'note' => $note === '' ? null : $note, 'consent_given_on' => $given, 'consent_method' => $method, 'consent_expires_on' => $expires, 'added_by' => $staff->user->id,
            ]);
            $this->applications->addNote((string) $application['id'], $staff->user->id, 'talent_pool', "Added to the talent pool with the candidate's consent (given on {$given}; consent lapses on {$expires}).");
            $this->audit->record(new AuditEvent('talent_pool.added', AuditEvent::OUTCOME_SUCCESS, 'talent_pool', $id, $staff->user->id, $context->requestId));
        });

        return ['id' => $id, 'consent_expires_on' => $expires];
    }

    /** Deletes the entry: consent withdrawn, so nothing is kept. */
    public function withdraw(string $id, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $entry = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? $this->pool->find($id) : null;
        if ($entry === null) {
            throw new ResourceNotFound('Talent pool entry not found.');
        }
        $this->transactions->transaction(function () use ($id, $entry, $staff, $context): void {
            $this->pool->delete($id);
            if ($entry['application_id'] !== null && $this->applications->find((string) $entry['application_id']) !== null) {
                $this->applications->addNote((string) $entry['application_id'], $staff->user->id, 'talent_pool', 'Removed from the talent pool: consent withdrawn. Nothing is kept.');
            }
            $this->audit->record(new AuditEvent('talent_pool.withdrawn', AuditEvent::OUTCOME_SUCCESS, 'talent_pool', $id, $staff->user->id, $context->requestId));
        });
    }

    private static function date(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date !== false && $date->format('Y-m-d') === trim($value) ? $date->format('Y-m-d') : null;
    }
}
