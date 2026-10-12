<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\Logger;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\Exception\Forbidden;
use Paxofi\CorporateWebsite\Application\Exception\RateLimited;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailSettings;
use Paxofi\CorporateWebsite\Application\Mail\Outbox;
use Paxofi\CorporateWebsite\Application\Media\MediaInspector;
use Paxofi\CorporateWebsite\Application\Media\MediaStorage;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * careers.paxofi.com (decisions D-018, D-019): published roles, CV upload and
 * applications. A CV is uploaded first (PDF or Word, up to 5 MB, checked by
 * its content) and claimed by the application within a day. Each application
 * gets a reference, an acknowledgement email to the candidate and an alert to
 * the recruitment team.
 */
final class CareersService
{
    public const CV_MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_PER_IP_HOUR = 5;
    private const MAX_PER_EMAIL_DAY = 3;
    private const MAX_UPLOADS_PER_IP_HOUR = 10;
    private const CV_TYPES = ['application/pdf' => 'PDF', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word'];

    /**
     * @param Closure(): DateTimeImmutable $clock
     * @param Closure(): string $uuid
     */
    public function __construct(
        private readonly CareerRoles $roles,
        private readonly JobApplications $applications,
        private readonly MediaStorage $cvStorage,
        private readonly Outbox $outbox,
        private readonly MailSettings $mail,
        private readonly RecruitmentSettings $recruitment,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Logger $logger,
        private readonly Closure $clock,
        private readonly Closure $uuid,
    ) {
    }

    /**
     * Whether applications are open, and the cohort an application made now
     * belongs to (D-027). The careers site shows the opening date until then.
     *
     * @return array{applications_open: bool, opens_at: ?string, opens_on: ?string, cohort: ?string, cohort1_closes_on: ?string}
     */
    public function status(): array
    {
        $now = ($this->clock)();
        $lagos = new \DateTimeZone('Africa/Lagos');
        $opensAt = $this->recruitment->opensAt;
        $closes = $this->recruitment->cohort1ClosesAt;

        return [
            'applications_open' => $this->recruitment->open($now),
            'opens_at' => $opensAt?->setTimezone($lagos)->format(DATE_ATOM),
            'opens_on' => $opensAt?->setTimezone($lagos)->format('l j F Y'),
            'cohort' => $this->recruitment->cohort($now),
            'cohort1_closes_on' => $closes?->setTimezone($lagos)->modify('-1 day')->format('l j F Y'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function roles(): array
    {
        return array_map(static fn (CareerRole $role): array => $role->toPublicArray(), $this->roles->published());
    }

    /** @return array<string, mixed> */
    public function role(string $slug): array
    {
        $role = $this->publishedRole($slug);

        return $role->toPublicArray();
    }

    /** @return array{token: string, filename: string, size_bytes: int, format: string} */
    public function uploadCv(string $bytes, string $filename, RequestContext $context): array
    {
        if (!$this->cvStorage->available()) {
            throw new DependencyUnavailable('CV uploads are not available right now. Add a portfolio or LinkedIn link instead, or email hr@paxofi.com.');
        }
        if ($this->applications->recentUploads($context->clientIp, ($this->clock)()) >= self::MAX_UPLOADS_PER_IP_HOUR) {
            throw new RateLimited(3600);
        }
        if (strlen($bytes) > self::CV_MAX_BYTES) {
            throw new ValidationFailed(['cv' => 'Your CV can be up to 5 MB.'], 'Your CV can be up to 5 MB.');
        }
        try {
            $detected = (new MediaInspector())->inspect($bytes, $filename);
        } catch (ValidationFailed) {
            $detected = null;
        }
        if ($detected === null || !isset(self::CV_TYPES[$detected->mimeType])) {
            throw new ValidationFailed(['cv' => 'Upload your CV as a PDF or Word (.docx) file.'], 'Upload your CV as a PDF or Word (.docx) file.');
        }
        $base = trim((string) preg_replace('/[^A-Za-z0-9._ -]+/', '', pathinfo($filename, PATHINFO_FILENAME))) ?: 'CV';
        $clean = mb_substr($base, 0, 120) . '.' . $detected->extension;

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $reference = ($this->uuid)();
        $this->cvStorage->put($reference, $bytes);
        $this->applications->addUpload(hash('sha256', $token), $reference, $clean, $detected->mimeType, strlen($bytes), $context->clientIp);

        return ['token' => $token, 'filename' => $clean, 'size_bytes' => strlen($bytes), 'format' => self::CV_TYPES[$detected->mimeType]];
    }

    /** @return array{reference: string, role: string} */
    public function apply(string $slug, mixed $input, RequestContext $context): array
    {
        $role = $this->publishedRole($slug);
        $now = ($this->clock)();
        // Staff with the test code can submit controlled test applications before opening (Gate 1); they are flagged and left out of reports.
        $testing = $this->recruitment->isTestCode(is_array($input) ? ($input['test_code'] ?? null) : null);
        if (!$testing && !$this->recruitment->open($now)) {
            $opens = $this->status()['opens_on'];
            throw new Forbidden('Applications are not open yet' . ($opens === null ? '.' : ". They open on {$opens}."), 'APPLICATIONS_NOT_OPEN');
        }
        $values = ApplicationInput::validate($input, $role->engagementType);
        if ($values['likely_spam']) {
            $this->logger->info('application.honeypot_triggered', ['request_id' => $context->requestId]);

            return ['reference' => 'PIF-' . self::code(), 'role' => $role->title];
        }
        $recent = $this->applications->recentCounts($context->clientIp, $values['email'], $now);
        if (!$testing && ($recent['ip'] >= self::MAX_PER_IP_HOUR || $recent['email'] >= self::MAX_PER_EMAIL_DAY)) {
            throw new RateLimited(3600);
        }
        if ($this->applications->hasOpenApplication($values['email'], $role->id)) {
            throw new ValidationFailed(['email' => 'You already have an application in progress for this role. We will email you about it; there is no need to apply again.'], 'You already have an application in progress for this role.');
        }

        $id = ($this->uuid)();
        do {
            $reference = 'PIF-' . self::code();
        } while ($this->applications->referenceExists($reference));

        $values['engagement_type'] = $role->engagementType->value;
        $values['cohort'] = $this->recruitment->cohort($now);
        $values['is_test'] = $testing;
        $timeline = $this->timeline($values['cohort']);
        $this->transactions->transaction(function () use ($id, $reference, $role, $values, $context, $now, $timeline, $testing): void {
            $cv = null;
            if ($values['cv_token'] !== null) {
                $cv = $this->applications->claimUpload(hash('sha256', $values['cv_token']), $now);
                if ($cv === null) {
                    throw new ValidationFailed(['cv' => 'Your CV upload has expired. Please upload it again.'], 'Your CV upload has expired. Please upload it again.');
                }
            }
            $this->applications->add($id, $reference, $role->id, $values, $cv, $context);
            $this->audit->record(new AuditEvent('application.submitted', AuditEvent::OUTCOME_SUCCESS, 'application', $id, requestId: $context->requestId));
            if (!$this->mail->enabled) {
                return;
            }
            $application = ['full_name' => $values['full_name'], 'role_title' => $role->title, 'reference' => $reference, 'hours_per_week' => $values['hours_per_week'], 'engagement_type' => $role->engagementType->value];
            $template = CandidateEmails::TEMPLATES['acknowledgement'];
            $fill = CandidateEmails::values($application, $this->recruitment->careersSiteUrl . '/privacy', $timeline);
            $this->outbox->add(new Email([$values['email']], CandidateEmails::fill($template['subject'], $fill), CandidateEmails::fill($template['body'], $fill) . "\n", $this->recruitment->replyTo, 'candidate_acknowledgement'));
            if ($this->recruitment->alertTo !== []) {
                $this->outbox->add(new Email($this->recruitment->alertTo, ($testing ? '[TEST] ' : '') . "New application: {$role->title} ({$reference})", implode("\n", [
                    ($testing ? 'A TEST application arrived' : 'A new application arrived') . ' on ' . $this->recruitment->careersSiteUrl . '.',
                    '',
                    'Role: ' . $role->title,
                    'Engagement type: ' . $role->engagementType->label(),
                    'Reference: ' . $reference,
                    'Name: ' . Email::oneLine($values['full_name']),
                    'Hours a week: ' . $values['hours_per_week'],
                    'CV attached in the staff area: ' . ($cv === null ? 'no (links only)' : 'yes'),
                    '',
                    'Review it in the staff area (sign in first): ' . $this->mail->staffLink('/admin/recruitment/' . $id),
                ]) . "\n", kind: 'application_alert'));
            }
        });

        return ['reference' => $reference, 'role' => $role->title];
    }

    /** One paragraph for the acknowledgement email, from the cohort the application belongs to. */
    private function timeline(?string $cohort): string
    {
        $closes = $this->status()['cohort1_closes_on'];
        if ($cohort === 'Cohort 1' && $closes !== null) {
            return "Applications for Cohort 1 close on {$closes}. We review them after that date and email you at every step.";
        }
        if ($cohort === 'Cohort 2') {
            return 'Cohort 1 applications have closed, so we will consider your application for our talent pool and for the next cohort. We will email you if that changes.';
        }

        return 'We review applications as they come in and email you at every step.';
    }

    private function publishedRole(string $slug): CareerRole
    {
        $role = preg_match('/^[a-z0-9-]{1,120}$/', $slug) === 1 ? $this->roles->bySlug($slug) : null;
        if ($role === null || $role->state !== CareerRole::PUBLISHED) {
            throw new ResourceNotFound('This role is not open for applications.');
        }

        return $role;
    }

    /** Six characters without look-alikes (no 0/O, 1/I/L). */
    private static function code(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }
}
