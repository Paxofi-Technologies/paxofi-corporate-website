<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

/** mail_settings (migration 024, D-031): SMTP settings saved in the staff area. The password is stored encrypted. */
interface MailSettingsStore
{
    /** The settings an administrator can save, as the keys used in this table. */
    public const KEYS = ['transport', 'host', 'port', 'encryption', 'username', 'password', 'from_address', 'from_name'];

    /** @return array<string, string> only the settings that have a value */
    public function all(): array;

    /** Sets a value, or removes the setting when $value is null. */
    public function set(string $key, ?string $value, ?string $userId): void;
}
