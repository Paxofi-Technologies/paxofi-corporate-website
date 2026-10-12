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
 * Participation certificates and their public check (decision D-029). A
 * certificate is issued for a participant whose record is Completed or Alumni.
 * It has a random code that cannot be guessed; anyone who holds the code can
 * check it at careers.paxofi.com/verify and sees only the facts printed on the
 * certificate. A revoked certificate shows only that it is no longer valid.
 */
final class CertificateService
{
    /** @var list<ApplicationStage> */
    public const ELIGIBLE_STAGES = [ApplicationStage::Completed, ApplicationStage::Alumni];
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly Certificates $certificates,
        private readonly JobApplications $applications,
        private readonly string $careersSiteUrl,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $clock,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function forApplication(string $applicationId): array
    {
        return array_map(fn (array $row): array => $this->summary($row), $this->certificates->forApplication($applicationId));
    }

    /**
     * Issues a certificate for an application the viewer may see.
     *
     * @param array<string, mixed> $application an application row
     * @return array<string, mixed>
     */
    public function issue(array $application, mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $input = is_array($input) ? $input : [];
        $stage = ApplicationStage::from((string) $application['stage']);
        if (!in_array($stage, self::ELIGIBLE_STAGES, true) || (int) ($application['is_test'] ?? 0) === 1) {
            throw new Conflict('A certificate can be issued once the participant is Completed or Alumni. Test applications get none.');
        }
        foreach ($this->certificates->forApplication((string) $application['id']) as $existing) {
            if ($existing['status'] === 'valid') {
                throw new Conflict('This participant already has a valid certificate. Revoke it first to issue another.');
            }
        }
        $today = ($this->clock)()->setTimezone(new DateTimeZone('Africa/Lagos'))->format('Y-m-d');
        $applied = substr((string) $application['created_at'], 0, 10);
        $errors = [];
        $completed = self::date($input['completed_on'] ?? null);
        if ($completed === null || $completed > $today || $completed < $applied) {
            $errors['completed_on'] = 'Enter the day the participant finished (not in the future), for example 2027-04-20.';
        }
        $started = null;
        if (($input['started_on'] ?? '') !== '' && ($input['started_on'] ?? null) !== null) {
            $started = self::date($input['started_on']);
            if ($started === null || $started < $applied || ($completed !== null && $started > $completed)) {
                $errors['started_on'] = 'Enter the start day (after they applied and not after the finish), or leave it empty.';
            }
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        $id = Uuid::v4();
        $code = $this->newCode();
        $this->transactions->transaction(function () use ($id, $code, $application, $started, $completed, $staff, $context): void {
            $this->certificates->add([
                'id' => $id, 'code' => $code, 'application_id' => $application['id'], 'holder_name' => $application['full_name'], 'role_title' => $application['role_title'],
                'engagement_type' => $application['engagement_type'] ?? 'fellow', 'cohort' => $application['cohort'] ?? null,
                'started_on' => $started, 'completed_on' => $completed, 'issued_by' => $staff->user->id,
            ]);
            $this->applications->addNote((string) $application['id'], $staff->user->id, 'certificate', "Certificate {$code} issued.");
            $this->audit->record(new AuditEvent('certificate.issued', AuditEvent::OUTCOME_SUCCESS, 'certificate', $id, $staff->user->id, $context->requestId));
        });

        return $this->summary($this->certificates->find($id) ?? []);
    }

    /** @return array{bytes: string, filename: string} */
    public function pdf(string $id): array
    {
        $row = $this->find($id);
        $type = EngagementType::tryFrom((string) $row['engagement_type']) ?? EngagementType::Fellow;
        $bytes = CertificatePdf::render([
            'holder_name' => (string) $row['holder_name'], 'role_title' => (string) $row['role_title'], 'engagement_label' => $type->label(),
            'cohort' => $row['cohort'] === null ? null : (string) $row['cohort'], 'started_on' => $row['started_on'] === null ? null : (string) $row['started_on'],
            'completed_on' => (string) $row['completed_on'], 'code' => (string) $row['code'], 'issued_on' => substr((string) $row['issued_at'], 0, 10),
            'verify_url' => $this->verifyUrl(),
        ]);

        return ['bytes' => $bytes, 'filename' => 'Paxofi-certificate-' . $row['code'] . '.pdf'];
    }

    public function revoke(string $id, mixed $reason, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->find($id);
        $reason = is_string($reason) ? trim(preg_replace('/\s+/u', ' ', mb_scrub($reason, 'UTF-8')) ?? '') : '';
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw new ValidationFailed(['reason' => 'Say why the certificate is withdrawn (10 to 500 characters).'], 'Please correct the highlighted fields.');
        }
        $this->transactions->transaction(function () use ($id, $row, $reason, $staff, $context): void {
            if ($this->certificates->revoke($id, $reason, ($this->clock)()) === 0) {
                throw new Conflict('This certificate is already revoked.');
            }
            if ($row['application_id'] !== null && $this->applications->find((string) $row['application_id']) !== null) {
                $this->applications->addNote((string) $row['application_id'], $staff->user->id, 'certificate', "Certificate {$row['code']} revoked.");
            }
            $this->audit->record(new AuditEvent('certificate.revoked', AuditEvent::OUTCOME_SUCCESS, 'certificate', $id, $staff->user->id, $context->requestId));
        });

        return $this->summary($this->find($id));
    }

    /** Deletes the certificate record, for example on an erasure request. Its code then finds nothing. */
    public function delete(string $id, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $this->find($id);
        $this->transactions->transaction(function () use ($id, $staff, $context): void {
            $this->certificates->delete($id);
            $this->audit->record(new AuditEvent('certificate.deleted', AuditEvent::OUTCOME_SUCCESS, 'certificate', $id, $staff->user->id, $context->requestId));
        });
    }

    /**
     * The public check. An unknown or badly formed code gets the same answer,
     * so the page cannot be used to learn anything about codes.
     *
     * @return array<string, mixed>
     */
    public function verify(string $input): array
    {
        $code = self::normalise($input);
        $row = $code === null ? null : $this->certificates->findByCode($code);
        if ($row === null) {
            throw new ResourceNotFound('We could not find a certificate with that code. Check the code printed on the certificate and try again.');
        }
        if ($row['status'] === 'revoked') {
            return ['code' => $row['code'], 'valid' => false, 'status' => 'revoked', 'message' => 'This certificate is no longer valid. It was withdrawn by Paxofi Technologies.'];
        }
        $type = EngagementType::tryFrom((string) $row['engagement_type']) ?? EngagementType::Fellow;

        return [
            'code' => $row['code'], 'valid' => true, 'status' => 'valid',
            'holder_name' => $row['holder_name'], 'role_title' => $row['role_title'],
            'engagement' => ['type' => $type->value, 'label' => $type->label()], 'cohort' => $row['cohort'],
            'started_on' => $row['started_on'], 'completed_on' => $row['completed_on'], 'issued_on' => substr((string) $row['issued_at'], 0, 10),
            'programme' => 'Paxofi Innovation Fellowship (PIF) 2026',
            'message' => 'This certificate is genuine. It records participation in an unpaid learning and development programme; it is not a record of employment.',
        ];
    }

    /** "pif k7m2 9qxa r4td" becomes "PIF-K7M2-9QXA-R4TD"; anything else is null. */
    public static function normalise(string $input): ?string
    {
        $compact = strtoupper(preg_replace('/[\s-]+/', '', trim($input)) ?? '');
        if (preg_match('/^PIF([ABCDEFGHJKMNPQRSTUVWXYZ2-9]{12})$/', $compact, $match) !== 1) {
            return null;
        }

        return 'PIF-' . implode('-', str_split($match[1], 4));
    }

    private function newCode(): string
    {
        do {
            $characters = '';
            for ($i = 0; $i < 12; $i++) {
                $characters .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = 'PIF-' . implode('-', str_split($characters, 4));
        } while ($this->certificates->findByCode($code) !== null);

        return $code;
    }

    /** @return array<string, mixed> */
    private function find(string $id): array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? $this->certificates->find($id) : null;
        if ($row === null) {
            throw new ResourceNotFound('Certificate not found.');
        }

        return $row;
    }

    private function verifyUrl(): string
    {
        return rtrim($this->careersSiteUrl, '/') . '/verify';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function summary(array $row): array
    {
        return [
            'id' => $row['id'], 'code' => $row['code'], 'status' => $row['status'], 'holder_name' => $row['holder_name'],
            'started_on' => $row['started_on'], 'completed_on' => $row['completed_on'], 'issued_at' => substr((string) $row['issued_at'], 0, 19),
            'revoked_at' => $row['revoked_at'] === null ? null : substr((string) $row['revoked_at'], 0, 19), 'revoked_reason' => $row['revoked_reason'],
            'verify_url' => $this->verifyUrl() . '?code=' . rawurlencode((string) $row['code']),
        ];
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
