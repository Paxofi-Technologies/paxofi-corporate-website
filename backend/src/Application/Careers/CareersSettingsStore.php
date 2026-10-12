<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/** careers_settings (migration 022, D-028): key/value settings an administrator edits in the staff area. */
interface CareersSettingsStore
{
    public const OPENS_FROM = 'opens_from';
    public const COHORT1_CLOSES = 'cohort1_closes';
    public const TEST_CODE_HASH = 'test_code_hash';
    /** The last day applications are accepted (D-030). */
    public const CLOSES_AFTER = 'closes_after';

    /** @return array<string, string> only the settings that have a value */
    public function all(): array;

    /** Sets a value, or removes the setting when $value is null. */
    public function set(string $key, ?string $value, ?string $userId): void;
}
