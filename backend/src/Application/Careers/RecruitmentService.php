<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\Forbidden;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Mail\Attachment;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailSettings;
use Paxofi\CorporateWebsite\Application\Mail\Outbox;
use Paxofi\CorporateWebsite\Application\Media\MediaInspector;
use Paxofi\CorporateWebsite\Application\Media\MediaStorage;
use Paxofi\CorporateWebsite\Application\RequestContext;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/**
 * The staff Recruitment section (decision D-019): the application pipeline,
 * scorecards, notes, candidate emails and CV downloads. Every change and every
 * CV download is audited (without personal data in the audit log).
 */
final class RecruitmentService
{
    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly JobApplications $applications,
        private readonly CareerRoles $roles,
        private readonly MediaStorage $cvStorage,
        private readonly Outbox $outbox,
        private readonly MailSettings $mail,
        private readonly RecruitmentSettings $recruitment,
        private readonly ApplicationReviewers $reviewers,
        private readonly TalentPoolService $talentPool,
        private readonly CertificateService $certificates,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $clock,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function list(array $query, AuthenticatedStaff $staff): array
    {
        $filters = [];
        if (is_string($query['stage'] ?? null) && ApplicationStage::tryFrom($query['stage']) !== null) {
            $filters['stage'] = $query['stage'];
        }
        if (is_string($query['role'] ?? null) && preg_match('/^[0-9a-f-]{36}$/', $query['role']) === 1) {
            $filters['role'] = $query['role'];
        }
        if (is_string($query['q'] ?? null) && trim($query['q']) !== '') {
            $filters['q'] = mb_substr(trim($query['q']), 0, 100);
        }
        $filters['viewer_id'] = $staff->user->id;
        $filters['viewer_email'] = $staff->user->email;
        $page = max(1, (int) ($query['page'] ?? 1));
        $result = $this->applications->search($filters, $page, 25);
        $items = array_map(static function (array $row): array {
            $stage = ApplicationStage::from((string) $row['stage']);

            return [
                'id' => $row['id'], 'reference' => $row['reference'], 'full_name' => $row['full_name'], 'email' => $row['email'],
                'role_title' => $row['role_title'], 'engagement_type' => self::engagement($row)->value, 'engagement_label' => self::engagement($row)->label(),
                'cohort' => $row['cohort'] ?? null, 'is_test' => (int) ($row['is_test'] ?? 0) === 1,
                'stage' => $stage->value, 'stage_label' => $stage->label(), 'created_at' => $row['created_at'],
                'evidence_total' => $row['evidence_scores'] === null ? null : array_sum($row['evidence_scores']),
                'interview_total' => $row['interview_scores'] === null ? null : array_sum($row['interview_scores']),
                'has_cv' => $row['cv_reference'] !== null,
            ];
        }, $result['items']);

        return [
            'items' => $items,
            'meta' => [
                'page' => $page, 'per_page' => 25, 'total' => $result['total'], 'total_pages' => max(1, (int) ceil($result['total'] / 25)),
                'counts' => $result['counts'],
                'stages' => ApplicationStage::options(),
                'roles' => array_map(static fn (CareerRole $r): array => ['id' => $r->id, 'title' => $r->title], $this->roles->all()),
            ],
        ];
    }

    /** Acknowledge (move on from "Applied") within 2 working days (RB-19). */
    public const REVIEW_TARGET_WORKING_DAYS = 2;
    private const REPORT_PERIODS = [7, 30, 90];

    /**
     * The recruitment report (P3.1): applications by role, stage, channel and
     * campaign, and how quickly new applications are reviewed. Counts only.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function report(array $query): array
    {
        $days = (int) ($query['days'] ?? 30);
        $days = in_array($days, self::REPORT_PERIODS, true) ? $days : 0;
        $now = ($this->clock)();
        $data = $this->applications->report($days === 0 ? null : $now->modify("-{$days} days"));
        $stageLabel = static fn (?string $key): string => ($key !== null ? ApplicationStage::tryFrom($key)?->label() : null) ?? (string) $key;
        $sourceLabel = static fn (?string $key): string => $key === null ? 'Not given' : (ApplicationInput::SOURCES[$key] ?? $key);
        $label = static fn (array $rows, Closure $name): array => array_map(static fn (array $row): array => ['key' => $row['key'], 'label' => $name($row['key']), 'count' => $row['count']], $rows);

        $reviewed = 0;
        $onTime = 0;
        $waiting = 0;
        $overdue = 0;
        $hours = [];
        foreach ($data['review_times'] as $times) {
            $created = new DateTimeImmutable($times['created_at'], new \DateTimeZone('UTC'));
            $due = self::addWorkingDays($created, self::REVIEW_TARGET_WORKING_DAYS);
            if ($times['first_reviewed_at'] === null) {
                $waiting++;
                $overdue += $now > $due ? 1 : 0;
                continue;
            }
            $reviewedAt = new DateTimeImmutable($times['first_reviewed_at'], new \DateTimeZone('UTC'));
            $reviewed++;
            $onTime += $reviewedAt <= $due ? 1 : 0;
            $hours[] = max(0, ($reviewedAt->getTimestamp() - $created->getTimestamp()) / 3600);
        }
        sort($hours);
        $median = $hours === [] ? null : (count($hours) % 2 === 1 ? $hours[intdiv(count($hours), 2)] : ($hours[count($hours) / 2 - 1] + $hours[count($hours) / 2]) / 2);

        return [
            'days' => $days === 0 ? null : $days,
            'total' => $data['total'],
            'by_role' => $label($data['by_role'], static fn (?string $k): string => (string) $k),
            'by_stage' => $label($data['by_stage'], $stageLabel),
            'by_source' => $label($data['by_source'], $sourceLabel),
            'by_campaign' => $label($data['by_campaign'], static fn (?string $k): string => $k ?? 'No campaign link'),
            'by_day' => array_values(array_map(static fn (array $row): array => ['day' => (string) $row['key'], 'count' => $row['count']], array_reverse($data['by_day']))),
            'review' => [
                'target_working_days' => self::REVIEW_TARGET_WORKING_DAYS,
                'reviewed' => $reviewed,
                'on_time' => $onTime,
                'waiting' => $waiting,
                'overdue' => $overdue,
                'median_hours' => $median === null ? null : round($median, 1),
            ],
        ];
    }

    /** The moment N working days (Monday to Friday, Lagos time) after the given one. */
    public static function addWorkingDays(DateTimeImmutable $from, int $days): DateTimeImmutable
    {
        $at = $from->setTimezone(new \DateTimeZone('Africa/Lagos'));
        while ($days > 0) {
            $at = $at->modify('+1 day');
            if ((int) $at->format('N') <= 5) {
                $days--;
            }
        }

        return $at;
    }

    /** @return array<string, mixed> */
    public function get(string $id, AuthenticatedStaff $staff): array
    {
        $row = $this->findFor($id, $staff);
        $stage = ApplicationStage::from((string) $row['stage']);
        $type = self::engagement($row);

        return [
            'id' => $row['id'],
            'reference' => $row['reference'],
            'role' => ['id' => $row['opportunity_id'], 'title' => $row['role_title'], 'slug' => $row['role_slug']],
            'engagement' => $type->toPublicArray(),
            'cohort' => $row['cohort'] ?? null,
            'is_test' => (int) ($row['is_test'] ?? 0) === 1,
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'location' => $row['location'],
            'hours_per_week' => (int) $row['hours_per_week'],
            'portfolio_url' => $row['portfolio_url'],
            'linkedin_url' => $row['linkedin_url'],
            'motivation' => $row['motivation'],
            'experience' => $row['experience'],
            'source' => $row['source'] === null ? null : ['value' => $row['source'], 'label' => ApplicationInput::SOURCES[$row['source']] ?? $row['source']],
            'campaign' => $row['utm_source'] === null && $row['utm_campaign'] === null ? null : ['source' => $row['utm_source'], 'medium' => $row['utm_medium'], 'campaign' => $row['utm_campaign']],
            'cv' => $row['cv_reference'] === null ? null : ['filename' => $row['cv_filename'], 'size_bytes' => (int) $row['cv_size'], 'media_type' => $row['cv_media_type']],
            'stage' => $stage->value,
            'stage_label' => $stage->label(),
            'stage_changed_at' => $row['stage_changed_at'],
            'closed_at' => $row['closed_at'],
            'created_at' => $row['created_at'],
            'evidence' => Scorecard::summary($type, 'evidence', $row['evidence_scores']),
            'interview' => Scorecard::summary($type, 'interview', $row['interview_scores']),
            'notes' => $this->applications->notes($id),
            'stages' => ApplicationStage::options(),
            'scorecards' => Scorecard::definitions($type),
            'email_templates' => CandidateEmails::forApplication($row, $this->recruitment->careersSiteUrl . '/privacy'),
            'email_available' => $this->mail->enabled,
            'reviewers' => $this->reviewerList($id, $staff),
            'reviewer_candidates' => $staff->user->can(Permission::RECRUITMENT_MANAGE) ? $this->reviewers->eligibleStaff() : [],
            'talent_pool' => $this->talentPool->forApplication($id),
            'talent_pool_eligible' => in_array($stage, TalentPoolService::ELIGIBLE_STAGES, true),
            'certificates' => $this->certificates->forApplication($id),
            'certificate_eligible' => in_array($stage, CertificateService::ELIGIBLE_STAGES, true) && (int) ($row['is_test'] ?? 0) !== 1,
        ];
    }

    /**
     * Who is assigned to review, and who stepped aside. Only people who manage
     * recruitment see the reasons for a recusal.
     *
     * @return list<array<string, mixed>>
     */
    private function reviewerList(string $applicationId, AuthenticatedStaff $staff): array
    {
        $showReasons = $staff->user->can(Permission::RECRUITMENT_MANAGE);

        return array_map(static fn (array $r): array => [
            'user_id' => $r['user_id'],
            'name' => $r['name'],
            'status' => $r['status'],
            'reason' => $showReasons ? $r['reason'] : null,
            'created_at' => $r['created_at'],
        ], $this->reviewers->forApplication($applicationId));
    }

    public function changeStage(string $id, mixed $value, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->findFor($id, $staff);
        $stage = is_string($value) ? ApplicationStage::tryFrom($value) : null;
        if ($stage === null) {
            throw new ValidationFailed(['stage' => 'Choose a stage from the list.'], 'Please correct the highlighted fields.');
        }
        $from = ApplicationStage::from((string) $row['stage']);
        if ($from !== $stage) {
            $this->transactions->transaction(function () use ($id, $from, $stage, $staff, $context): void {
                $this->applications->setStage($id, $stage, ($this->clock)());
                $this->applications->addNote($id, $staff->user->id, 'stage', "Stage changed from {$from->label()} to {$stage->label()}.");
                $this->audit->record(new AuditEvent('application.stage_changed', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
            });
        }

        return $this->get($id, $staff);
    }

    public function score(string $id, mixed $gate, mixed $scores, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->findFor($id, $staff);
        $this->requireScoringReviewer($id, $staff);
        $type = self::engagement($row);
        $gate = is_string($gate) ? $gate : '';
        $clean = Scorecard::validate($type, $gate, $scores);
        $summary = Scorecard::summary($type, $gate, $clean);
        $label = Scorecard::definitions($type)[$gate]['label'];
        $this->transactions->transaction(function () use ($id, $gate, $clean, $summary, $label, $staff, $context): void {
            $this->applications->setScores($id, $gate, $clean);
            $this->applications->addNote($id, $staff->user->id, 'score', sprintf('%s scored %d/%d (%s).', $label, $summary['total'], $summary['out_of'], $summary['passed'] ? 'meets the progression mark' : 'below the progression mark'));
            $this->audit->record(new AuditEvent('application.scored', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id, $staff);
    }

    public function addNote(string $id, mixed $body, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->findFor($id, $staff);
        $body = is_string($body) ? trim(str_replace(["\r\n", "\r"], "\n", mb_scrub($body, 'UTF-8'))) : '';
        if (mb_strlen($body) < 2 || mb_strlen($body) > 4000) {
            throw new ValidationFailed(['body' => 'Write a note of up to 4,000 characters.'], 'Please correct the highlighted fields.');
        }
        $this->transactions->transaction(function () use ($id, $body, $staff, $context): void {
            $this->applications->addNote($id, $staff->user->id, 'note', $body);
            $this->audit->record(new AuditEvent('application.note_added', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id, $staff);
    }

    /** Largest file staff can attach to a candidate email (the agreement). */
    public const ATTACHMENT_MAX_BYTES = 5 * 1024 * 1024;
    /** Templates that promise an attachment, so cannot be sent without one. */
    private const NEEDS_ATTACHMENT = ['selection' => 'Attach the offer letter and agreement as one PDF or Word file before sending.'];

    /**
     * Sends a candidate email (edited from a template), with an optional PDF or
     * Word attachment, and records it on the application.
     */
    public function emailCandidate(string $id, array $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->findFor($id, $staff);
        if (!$this->mail->enabled) {
            throw new Conflict('Email sending is not set up yet (deployment guide Step 10c). Email the candidate from the HR mailbox instead.');
        }
        $subject = Email::oneLine(is_string($input['subject'] ?? null) ? $input['subject'] : '');
        $body = is_string($input['body'] ?? null) ? trim(str_replace(["\r\n", "\r"], "\n", mb_scrub($input['body'], 'UTF-8'))) : '';
        $template = is_string($input['template'] ?? null) && isset(CandidateEmails::TEMPLATES[$input['template']]) ? $input['template'] : 'custom';
        $errors = [];
        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 200) {
            $errors['subject'] = 'Enter a subject of up to 200 characters.';
        }
        if (mb_strlen($body) < 10 || mb_strlen($body) > 8000) {
            $errors['body'] = 'Write the email (up to 8,000 characters).';
        }
        if (!isset($errors['body']) && preg_match('/\[[^\]]{2,60}\]/', $body) === 1) {
            $errors['body'] = 'Replace the [placeholders] in square brackets before sending.';
        }
        $attachment = null;
        try {
            $attachment = self::attachment($input['attachment'] ?? null);
        } catch (ValidationFailed $failure) {
            $errors['attachment'] = $failure->getMessage();
        }
        if ($attachment === null && !isset($errors['attachment']) && isset(self::NEEDS_ATTACHMENT[$template])) {
            $errors['attachment'] = self::NEEDS_ATTACHMENT[$template];
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        $this->transactions->transaction(function () use ($id, $row, $subject, $body, $template, $attachment, $staff, $context): void {
            $this->outbox->add(new Email([(string) $row['email']], $subject, $body . "\n", $this->recruitment->replyTo, 'candidate_' . $template, $attachment === null ? [] : [$attachment]));
            $note = "Email sent: {$subject}" . ($attachment === null ? '' : "\nAttached: {$attachment->filename}") . "\n\n{$body}";
            $this->applications->addNote($id, $staff->user->id, 'email', $note);
            $this->audit->record(new AuditEvent('application.emailed', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id, $staff);
    }

    /**
     * The optional attachment, sent as {filename, content_base64}: a PDF or
     * Word .docx of up to 5 MB, checked by its content.
     */
    private static function attachment(mixed $value): ?Attachment
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }
        $filename = is_array($value) && is_string($value['filename'] ?? null) ? $value['filename'] : '';
        $encoded = is_array($value) && is_string($value['content_base64'] ?? null) ? $value['content_base64'] : '';
        $bytes = base64_decode($encoded, true);
        if ($bytes === false || $bytes === '') {
            throw new ValidationFailed([], 'The attachment could not be read. Choose the file again.');
        }
        if (strlen($bytes) > self::ATTACHMENT_MAX_BYTES) {
            throw new ValidationFailed([], 'Attachments can be up to 5 MB.');
        }
        try {
            $detected = (new MediaInspector())->inspect($bytes, $filename);
        } catch (ValidationFailed) {
            $detected = null;
        }
        if ($detected === null || !in_array($detected->mimeType, ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], true)) {
            throw new ValidationFailed([], 'Attach a PDF or Word (.docx) file.');
        }
        $base = pathinfo($filename, PATHINFO_FILENAME) ?: 'PIF-offer-and-agreement';

        return new Attachment($base . '.' . $detected->extension, $detected->mimeType, $bytes);
    }

    /** @return array{bytes: string, filename: string, media_type: string} */
    public function cv(string $id, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->findFor($id, $staff);
        $bytes = $row['cv_reference'] === null ? null : $this->cvStorage->get((string) $row['cv_reference']);
        if ($bytes === null) {
            throw new ResourceNotFound('This application has no CV file.');
        }
        $this->audit->record(new AuditEvent('application.cv_downloaded', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        $reference = str_replace('PIF-', '', (string) $row['reference']);
        $extension = pathinfo((string) $row['cv_filename'], PATHINFO_EXTENSION) ?: 'pdf';

        return ['bytes' => $bytes, 'filename' => "CV-{$reference}." . $extension, 'media_type' => (string) $row['cv_media_type']];
    }

    /** Erases an application, its notes and its CV (for example on the candidate's request). */
    public function delete(string $id, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $row = $this->findFor($id, $staff);
        $this->transactions->transaction(function () use ($id, $staff, $context): void {
            // Erasing an application also erases the person's talent pool entry (their data, their request).
            $this->talentPool->deleteForApplication($id);
            $this->applications->delete($id);
            $this->audit->record(new AuditEvent('application.deleted', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });
        if ($row['cv_reference'] !== null) {
            $this->cvStorage->delete((string) $row['cv_reference']);
        }
    }

    /** @return array<string, mixed> */
    public function addToTalentPool(string $id, mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->talentPool->add($this->findFor($id, $staff), $input, $staff, $context);

        return $this->get($id, $staff);
    }

    /** @return array<string, mixed> */
    public function issueCertificate(string $id, mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->certificates->issue($this->findFor($id, $staff), $input, $staff, $context);

        return $this->get($id, $staff);
    }

    /** Most people who can be assigned to one application. */
    public const MAX_REVIEWERS = 5;

    /**
     * Assigns a staff member to review an application (D-029). Not someone who
     * has stepped aside, and not a person who is the applicant.
     *
     * @return array<string, mixed>
     */
    public function assignReviewer(string $id, mixed $userId, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->findFor($id, $staff);
        $candidates = array_column($this->reviewers->eligibleStaff(), 'name', 'id');
        if (!is_string($userId) || !isset($candidates[$userId])) {
            throw new ValidationFailed(['user_id' => 'Choose a person from the list.'], 'Please correct the highlighted fields.');
        }
        $existing = $this->reviewers->find($id, $userId);
        if ($existing !== null) {
            throw new Conflict($existing['status'] === ApplicationReviewers::RECUSED
                ? 'This person has declared a conflict of interest on this application and cannot be assigned.'
                : 'This person is already a reviewer.');
        }
        if (self::isApplicant($row, $this->reviewers->emailOf($userId))) {
            throw new Conflict('This person is the applicant and cannot review their own application.');
        }
        if (count(array_filter($this->reviewers->forApplication($id), static fn (array $r): bool => $r['status'] === ApplicationReviewers::ASSIGNED)) >= self::MAX_REVIEWERS) {
            throw new Conflict('An application can have up to ' . self::MAX_REVIEWERS . ' reviewers.');
        }
        $this->transactions->transaction(function () use ($id, $userId, $candidates, $staff, $context): void {
            $this->reviewers->assign(Uuid::v4(), $id, $userId, $staff->user->id);
            $this->applications->addNote($id, $staff->user->id, 'reviewer', "{$candidates[$userId]} was assigned as a reviewer.");
            $this->audit->record(new AuditEvent('application.reviewer_assigned', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id, $staff);
    }

    /** @return array<string, mixed> */
    public function removeReviewer(string $id, string $userId, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->findFor($id, $staff);
        $name = array_column($this->reviewers->forApplication($id), 'name', 'user_id')[$userId] ?? null;
        $this->transactions->transaction(function () use ($id, $userId, $name, $staff, $context): void {
            if ($this->reviewers->remove($id, $userId) === 0) {
                throw new ResourceNotFound('This person is not an assigned reviewer. A recusal stays on the record.');
            }
            $this->applications->addNote($id, $staff->user->id, 'reviewer', "{$name} was removed as a reviewer.");
            $this->audit->record(new AuditEvent('application.reviewer_removed', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id, $staff);
    }

    /**
     * A staff member declares a conflict of interest and steps aside (D-029).
     * From then on the application is hidden from them. A scorer's earlier
     * scorecards are cleared, so a person with a conflict leaves no scores
     * behind; the next reviewer scores again.
     *
     * @return array{recused: true}
     */
    public function recuse(string $id, mixed $reason, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->findFor($id, $staff);
        $reason = is_string($reason) ? trim(preg_replace('/\s+/u', ' ', mb_scrub($reason, 'UTF-8')) ?? '') : '';
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw new ValidationFailed(['reason' => 'Say why you cannot review this application (10 to 500 characters).'], 'Please correct the highlighted fields.');
        }
        $this->transactions->transaction(function () use ($id, $reason, $staff, $context): void {
            $scored = $this->applications->scoredBy($id, $staff->user->id);
            $this->reviewers->recuse(Uuid::v4(), $id, $staff->user->id, $reason);
            if ($scored) {
                $this->applications->clearScores($id);
            }
            $this->applications->addNote($id, $staff->user->id, 'recusal', "{$staff->user->displayName} declared a conflict of interest and stepped aside." . ($scored ? ' Their scorecards were cleared and need scoring again.' : ''));
            $this->audit->record(new AuditEvent('application.recused', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });

        return ['recused' => true];
    }

    /**
     * When reviewers are assigned, only they may score. With none assigned,
     * anyone who manages recruitment may (as before release 28).
     */
    private function requireScoringReviewer(string $id, AuthenticatedStaff $staff): void
    {
        $assigned = array_column(array_filter($this->reviewers->forApplication($id), static fn (array $r): bool => $r['status'] === ApplicationReviewers::ASSIGNED), 'user_id');
        if ($assigned !== [] && !in_array($staff->user->id, $assigned, true)) {
            throw new Forbidden('Only the reviewers assigned to this application can score it.');
        }
    }

    /** @param array<string, mixed> $row an application row */
    private static function isApplicant(array $row, ?string $staffEmail): bool
    {
        return $staffEmail !== null && strtolower(trim((string) $row['email'])) === strtolower(trim($staffEmail));
    }

    /**
     * The application, unless the viewer stepped aside or is the applicant
     * (reported as "not found", so a recused person learns nothing more).
     *
     * @return array<string, mixed>
     */
    private function findFor(string $id, AuthenticatedStaff $staff): array
    {
        $row = $this->find($id);
        if (self::isApplicant($row, $staff->user->email) || ($this->reviewers->find($id, $staff->user->id)['status'] ?? null) === ApplicationReviewers::RECUSED) {
            throw new ResourceNotFound('Application not found.');
        }

        return $row;
    }

    /** @param array<string, mixed> $row an application row */
    private static function engagement(array $row): EngagementType
    {
        return EngagementType::tryFrom((string) ($row['engagement_type'] ?? '')) ?? EngagementType::Fellow;
    }

    /** @return array<string, mixed> */
    private function find(string $id): array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? $this->applications->find($id) : null;
        if ($row === null) {
            throw new ResourceNotFound('Application not found.');
        }

        return $row;
    }
}
