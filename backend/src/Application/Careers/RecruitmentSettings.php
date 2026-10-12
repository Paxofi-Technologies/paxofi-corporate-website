<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Careers site and recruitment settings (D-018, D-019, D-027).
 *
 * - opensAt: applications are refused before this moment (CAREERS_APPLICATIONS_OPEN_FROM, a Lagos date such as 2026-11-02). Null: always open.
 * - cohort1ClosesAt: applications received before this moment are for Cohort 1, later ones for Cohort 2 (CAREERS_COHORT1_CLOSES, the last Lagos day, such as 2026-11-15). Null: no cohort label.
 * - testCode: a secret that lets staff submit controlled test applications before opening (CAREERS_TEST_CODE, 16 characters or more). Empty: tests are off.
 * - testCodeHash: the SHA-256 of a test code an administrator generated in the staff area (D-028); works in addition to testCode.
 */
final readonly class RecruitmentSettings
{
    /** @param list<string> $alertTo */
    public function __construct(
        public array $alertTo = [],
        public string $replyTo = 'hr@paxofi.com',
        public string $careersSiteUrl = 'https://careers.paxofi.com',
        public ?DateTimeImmutable $opensAt = null,
        public ?DateTimeImmutable $cohort1ClosesAt = null,
        public string $testCode = '',
        public string $testCodeHash = '',
    ) {
    }

    public function open(DateTimeImmutable $now): bool
    {
        return $this->opensAt === null || $now >= $this->opensAt;
    }

    /** "Cohort 1" until the review window closes, then "Cohort 2"; null when no closing date is set. */
    public function cohort(DateTimeImmutable $now): ?string
    {
        return $this->cohort1ClosesAt === null ? null : ($now < $this->cohort1ClosesAt ? 'Cohort 1' : 'Cohort 2');
    }

    public function isTestCode(mixed $given): bool
    {
        if (!is_string($given) || $given === '') {
            return false;
        }

        return ($this->testCode !== '' && hash_equals($this->testCode, $given))
            || ($this->testCodeHash !== '' && hash_equals($this->testCodeHash, hash('sha256', $given)));
    }

    /**
     * The same settings with what an administrator saved in the staff area
     * (CareersSettingsStore) applied on top: a saved value wins over the server's.
     *
     * @param array<string, string> $saved
     */
    public function withSaved(array $saved): self
    {
        return new self(
            $this->alertTo,
            $this->replyTo,
            $this->careersSiteUrl,
            isset($saved[CareersSettingsStore::OPENS_FROM]) ? (self::lagosDay($saved[CareersSettingsStore::OPENS_FROM]) ?? $this->opensAt) : $this->opensAt,
            isset($saved[CareersSettingsStore::COHORT1_CLOSES]) ? (self::lagosDay($saved[CareersSettingsStore::COHORT1_CLOSES], true) ?? $this->cohort1ClosesAt) : $this->cohort1ClosesAt,
            $this->testCode,
            $saved[CareersSettingsStore::TEST_CODE_HASH] ?? $this->testCodeHash,
        );
    }

    /** The opening day as a Lagos calendar day (2026-11-02), or null. */
    public function opensOnDay(): ?string
    {
        return $this->opensAt?->setTimezone(new DateTimeZone('Africa/Lagos'))->format('Y-m-d');
    }

    /** The last day for Cohort 1 as a Lagos calendar day (2026-11-15), or null. */
    public function cohort1LastDay(): ?string
    {
        return $this->cohort1ClosesAt?->setTimezone(new DateTimeZone('Africa/Lagos'))->modify('-1 day')->format('Y-m-d');
    }

    /**
     * A Lagos calendar day from the environment. The start of that day, or the
     * start of the next day when $endOfDay is true. Null when empty or invalid.
     */
    public static function lagosDay(?string $value, bool $endOfDay = false): ?DateTimeImmutable
    {
        $value = trim((string) $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Africa/Lagos'));
        if ($day === false || $day->format('Y-m-d') !== $value) {
            return null;
        }

        return $endOfDay ? $day->modify('+1 day') : $day;
    }

    public static function validTestCode(?string $value): string
    {
        $value = trim((string) $value);

        return strlen($value) >= 16 ? $value : '';
    }
}
