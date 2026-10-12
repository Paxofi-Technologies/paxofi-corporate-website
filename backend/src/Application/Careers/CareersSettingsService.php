<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * Staff area → Recruitment → Applications opening (D-028): the day applications
 * open, the last day for Cohort 1, and the staff test code. A saved value wins
 * over the server's setting of the same name; clearing it goes back to the
 * server's. Administrators only; every change is audited.
 */
final class CareersSettingsService
{
    /** @param Closure(): DateTimeImmutable $clock */
    public function __construct(
        private readonly CareersSettingsStore $store,
        private readonly RecruitmentSettingsSource $settings,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $clock,
    ) {
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        $saved = $this->store->all();
        $server = $this->settings->server();
        $now = ($this->clock)();
        $current = $this->settings->current();
        $source = static fn (string $key, ?string $serverValue): string => isset($saved[$key]) ? 'staff' : ($serverValue !== null ? 'server' : 'none');

        return [
            'opens_from' => $saved[CareersSettingsStore::OPENS_FROM] ?? null,
            'cohort1_closes' => $saved[CareersSettingsStore::COHORT1_CLOSES] ?? null,
            'server' => ['opens_from' => $server->opensOnDay(), 'cohort1_closes' => $server->cohort1LastDay()],
            'effective' => [
                'opens_from' => $current->opensOnDay(),
                'cohort1_closes' => $current->cohort1LastDay(),
                'opens_from_source' => $source(CareersSettingsStore::OPENS_FROM, $server->opensOnDay()),
                'cohort1_closes_source' => $source(CareersSettingsStore::COHORT1_CLOSES, $server->cohort1LastDay()),
                'applications_open' => $current->open($now),
                'cohort' => $current->cohort($now),
            ],
            'test_code' => ['set' => isset($saved[CareersSettingsStore::TEST_CODE_HASH]) || $server->testCode !== '', 'by_staff' => isset($saved[CareersSettingsStore::TEST_CODE_HASH]), 'by_server' => $server->testCode !== ''],
        ];
    }

    /**
     * Saves the two dates. An empty value clears the saved date, so the
     * server's setting (or "always open") applies again.
     *
     * @return array<string, mixed>
     */
    public function update(mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $input = is_array($input) ? $input : [];
        $errors = [];
        $values = [];
        foreach ([CareersSettingsStore::OPENS_FROM => 'opens_from', CareersSettingsStore::COHORT1_CLOSES => 'cohort1_closes'] as $key => $field) {
            $value = $input[$field] ?? null;
            $value = is_string($value) ? trim($value) : ($value === null ? '' : null);
            if ($value === null || ($value !== '' && RecruitmentSettings::lagosDay($value) === null)) {
                $errors[$field] = 'Enter a date such as 2026-11-02, or leave it empty.';
                continue;
            }
            $values[$key] = $value === '' ? null : $value;
        }
        if ($errors === []) {
            $opens = $values[CareersSettingsStore::OPENS_FROM] ?? $this->settings->server()->opensOnDay();
            $closes = $values[CareersSettingsStore::COHORT1_CLOSES] ?? $this->settings->server()->cohort1LastDay();
            if ($opens !== null && $closes !== null && $closes < $opens) {
                $errors['cohort1_closes'] = 'The last day for Cohort 1 must be on or after the opening day.';
            }
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        $this->transactions->transaction(function () use ($values, $staff, $context): void {
            foreach ($values as $key => $value) {
                $this->store->set($key, $value, $staff->user->id);
            }
            $this->audit->record(new AuditEvent('careers_settings.updated', AuditEvent::OUTCOME_SUCCESS, 'careers_settings', null, $staff->user->id, $context->requestId));
        });

        return $this->get();
    }

    /**
     * A new staff test code. It is shown once and only its hash is kept.
     *
     * @return array<string, mixed>
     */
    public function newTestCode(AuthenticatedStaff $staff, RequestContext $context): array
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = 'PIF-';
        for ($i = 0; $i < 20; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $this->transactions->transaction(function () use ($code, $staff, $context): void {
            $this->store->set(CareersSettingsStore::TEST_CODE_HASH, hash('sha256', $code), $staff->user->id);
            $this->audit->record(new AuditEvent('careers_settings.test_code_created', AuditEvent::OUTCOME_SUCCESS, 'careers_settings', null, $staff->user->id, $context->requestId));
        });

        return ['test_code' => $code] + $this->get();
    }

    /** @return array<string, mixed> */
    public function removeTestCode(AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->transactions->transaction(function () use ($staff, $context): void {
            $this->store->set(CareersSettingsStore::TEST_CODE_HASH, null, $staff->user->id);
            $this->audit->record(new AuditEvent('careers_settings.test_code_removed', AuditEvent::OUTCOME_SUCCESS, 'careers_settings', null, $staff->user->id, $context->requestId));
        });

        return $this->get();
    }
}
