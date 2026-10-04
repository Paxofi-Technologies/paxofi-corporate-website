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
        $values = ApplicationInput::validate($input);
        if ($values['likely_spam']) {
            $this->logger->info('application.honeypot_triggered', ['request_id' => $context->requestId]);

            return ['reference' => 'PIF-' . self::code(), 'role' => $role->title];
        }
        $now = ($this->clock)();
        $recent = $this->applications->recentCounts($context->clientIp, $values['email'], $now);
        if ($recent['ip'] >= self::MAX_PER_IP_HOUR || $recent['email'] >= self::MAX_PER_EMAIL_DAY) {
            throw new RateLimited(3600);
        }
        if ($this->applications->hasOpenApplication($values['email'], $role->id)) {
            throw new ValidationFailed(['email' => 'You already have an application in progress for this role. We will email you about it; there is no need to apply again.'], 'You already have an application in progress for this role.');
        }

        $id = ($this->uuid)();
        do {
            $reference = 'PIF-' . self::code();
        } while ($this->applications->referenceExists($reference));

        $this->transactions->transaction(function () use ($id, $reference, $role, $values, $context, $now): void {
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
            $application = ['full_name' => $values['full_name'], 'role_title' => $role->title, 'reference' => $reference, 'hours_per_week' => $values['hours_per_week']];
            $template = CandidateEmails::TEMPLATES['acknowledgement'];
            $fill = CandidateEmails::values($application);
            $this->outbox->add(new Email([$values['email']], CandidateEmails::fill($template['subject'], $fill), CandidateEmails::fill($template['body'], $fill) . "\n", $this->recruitment->replyTo, 'candidate_acknowledgement'));
            if ($this->recruitment->alertTo !== []) {
                $this->outbox->add(new Email($this->recruitment->alertTo, "New application: {$role->title} ({$reference})", implode("\n", [
                    'A new application arrived on ' . $this->recruitment->careersSiteUrl . '.',
                    '',
                    'Role: ' . $role->title,
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
