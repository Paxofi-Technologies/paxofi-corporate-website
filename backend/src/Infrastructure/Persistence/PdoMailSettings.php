<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\CorporateWebsite\Application\Mail\MailSettingsStore;

/** mail_settings (migration 024, D-031). */
final class PdoMailSettings implements MailSettingsStore
{
    use GuardedQueries;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function all(): array
    {
        $settings = [];
        foreach ($this->select('SELECT setting_key, setting_value FROM mail_settings WHERE setting_value IS NOT NULL') as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        return $settings;
    }

    public function set(string $key, ?string $value, ?string $userId): void
    {
        if ($value === null) {
            $this->write('DELETE FROM mail_settings WHERE setting_key = :key', ['key' => $key]);

            return;
        }
        $this->write(
            'INSERT INTO mail_settings (setting_key, setting_value, updated_by) VALUES (:key, :value, :user)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)',
            ['key' => $key, 'value' => $value, 'user' => $userId],
        );
    }
}
