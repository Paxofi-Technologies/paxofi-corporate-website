<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailSettings;
use Paxofi\CorporateWebsite\Application\Mail\Outbox;
use Paxofi\CorporateWebsite\Application\Media\MediaStorage;
use Paxofi\CorporateWebsite\Application\RequestContext;

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
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $clock,
    ) {
    }

    /** @param array<string, mixed> $query */
    public function list(array $query): array
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
        $page = max(1, (int) ($query['page'] ?? 1));
        $result = $this->applications->search($filters, $page, 25);
        $items = array_map(static function (array $row): array {
            $stage = ApplicationStage::from((string) $row['stage']);

            return [
                'id' => $row['id'], 'reference' => $row['reference'], 'full_name' => $row['full_name'], 'email' => $row['email'],
                'role_title' => $row['role_title'], 'stage' => $stage->value, 'stage_label' => $stage->label(), 'created_at' => $row['created_at'],
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

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        $row = $this->find($id);
        $stage = ApplicationStage::from((string) $row['stage']);

        return [
            'id' => $row['id'],
            'reference' => $row['reference'],
            'role' => ['id' => $row['opportunity_id'], 'title' => $row['role_title'], 'slug' => $row['role_slug']],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'location' => $row['location'],
            'hours_per_week' => (int) $row['hours_per_week'],
            'portfolio_url' => $row['portfolio_url'],
            'linkedin_url' => $row['linkedin_url'],
            'motivation' => $row['motivation'],
            'experience' => $row['experience'],
            'cv' => $row['cv_reference'] === null ? null : ['filename' => $row['cv_filename'], 'size_bytes' => (int) $row['cv_size'], 'media_type' => $row['cv_media_type']],
            'stage' => $stage->value,
            'stage_label' => $stage->label(),
            'stage_changed_at' => $row['stage_changed_at'],
            'closed_at' => $row['closed_at'],
            'created_at' => $row['created_at'],
            'evidence' => Scorecard::summary('evidence', $row['evidence_scores']),
            'interview' => Scorecard::summary('interview', $row['interview_scores']),
            'notes' => $this->applications->notes($id),
            'stages' => ApplicationStage::options(),
            'scorecards' => Scorecard::definitions(),
            'email_templates' => CandidateEmails::forApplication($row),
            'email_available' => $this->mail->enabled,
        ];
    }

    public function changeStage(string $id, mixed $value, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->find($id);
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

        return $this->get($id);
    }

    public function score(string $id, mixed $gate, mixed $scores, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->find($id);
        $gate = is_string($gate) ? $gate : '';
        $clean = Scorecard::validate($gate, $scores);
        $summary = Scorecard::summary($gate, $clean);
        $this->transactions->transaction(function () use ($id, $gate, $clean, $summary, $staff, $context): void {
            $this->applications->setScores($id, $gate, $clean);
            $this->applications->addNote($id, $staff->user->id, 'score', sprintf('%s scored %d/35 (%s).', Scorecard::GATES[$gate]['label'], $summary['total'], $summary['passed'] ? 'meets the progression mark' : 'below the progression mark'));
            $this->audit->record(new AuditEvent('application.scored', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id);
    }

    public function addNote(string $id, mixed $body, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->find($id);
        $body = is_string($body) ? trim(str_replace(["\r\n", "\r"], "\n", mb_scrub($body, 'UTF-8'))) : '';
        if (mb_strlen($body) < 2 || mb_strlen($body) > 4000) {
            throw new ValidationFailed(['body' => 'Write a note of up to 4,000 characters.'], 'Please correct the highlighted fields.');
        }
        $this->transactions->transaction(function () use ($id, $body, $staff, $context): void {
            $this->applications->addNote($id, $staff->user->id, 'note', $body);
            $this->audit->record(new AuditEvent('application.note_added', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id);
    }

    /** Sends a candidate email (edited from a template) and records it on the application. */
    public function emailCandidate(string $id, array $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->find($id);
        if (!$this->mail->enabled) {
            throw new Conflict('Email sending is not set up yet (deployment guide Step 10c). Email the candidate from the HR mailbox instead.');
        }
        $subject = Email::oneLine(is_string($input['subject'] ?? null) ? $input['subject'] : '');
        $body = is_string($input['body'] ?? null) ? trim(str_replace(["\r\n", "\r"], "\n", mb_scrub($input['body'], 'UTF-8'))) : '';
        $errors = [];
        if (mb_strlen($subject) < 3 || mb_strlen($subject) > 200) {
            $errors['subject'] = 'Enter a subject of up to 200 characters.';
        }
        if (mb_strlen($body) < 10 || mb_strlen($body) > 8000) {
            $errors['body'] = 'Write the email (up to 8,000 characters).';
        }
        if ($errors === [] && preg_match('/\[[^\]]{2,60}\]/', $body) === 1) {
            $errors['body'] = 'Replace the [placeholders] in square brackets before sending.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        $template = is_string($input['template'] ?? null) && isset(CandidateEmails::TEMPLATES[$input['template']]) ? $input['template'] : 'custom';
        $this->transactions->transaction(function () use ($id, $row, $subject, $body, $template, $staff, $context): void {
            $this->outbox->add(new Email([(string) $row['email']], $subject, $body . "\n", $this->recruitment->replyTo, 'candidate_' . $template));
            $this->applications->addNote($id, $staff->user->id, 'email', "Email sent: {$subject}\n\n{$body}");
            $this->audit->record(new AuditEvent('application.emailed', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });

        return $this->get($id);
    }

    /** @return array{bytes: string, filename: string, media_type: string} */
    public function cv(string $id, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->find($id);
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
        $row = $this->find($id);
        $this->transactions->transaction(function () use ($id, $staff, $context): void {
            $this->applications->delete($id);
            $this->audit->record(new AuditEvent('application.deleted', AuditEvent::OUTCOME_SUCCESS, 'application', $id, $staff->user->id, $context->requestId));
        });
        if ($row['cv_reference'] !== null) {
            $this->cvStorage->delete((string) $row['cv_reference']);
        }
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
