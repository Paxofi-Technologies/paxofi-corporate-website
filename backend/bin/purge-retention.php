<?php

declare(strict_types=1);

/*
 * Applies the data retention policy (decision D-008):
 *   - enquiries older than 24 months are deleted;
 *   - IP address and user-agent on enquiries older than 90 days are cleared;
 *   - audit events older than 24 months are deleted;
 *   - staff sign-in attempts older than 90 days and sessions ended more than
 *     30 days ago are deleted (D-009);
 *   - visitor analytics: daily totals after 25 months, visitor hashes and
 *     salts once their day is over (D-014);
 *   - sent emails after 30 days, failed ones after 90, password reset links a
 *     day after they expire (D-016).
 *
 *   php bin/purge-retention.php
 *
 * Schedule daily with cPanel → Cron Jobs (see docs/RUNBOOKS.md, RB-10).
 * Reads DB_* settings from the environment or backend/.env.
 */

use Paxofi\Core\Configuration\EnvLoader;
use Paxofi\Core\Configuration\Environment;
use Paxofi\CorporateWebsite\Application\Retention\RetentionPolicy;
use Paxofi\CorporateWebsite\Application\Retention\RetentionPurge;
use Paxofi\CorporateWebsite\Database\Connection;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $environment = Environment::from((new EnvLoader())->load(dirname(__DIR__) . '/.env'));
    $result = (new RetentionPurge(Connection::make($environment), new RetentionPolicy()))->run();
    fwrite(STDOUT, sprintf(
        "%s retention purge: %d enquiries deleted, %d enquiries' IP/user-agent cleared, %d audit events deleted, %d sign-in attempts deleted, %d sessions deleted, %d analytics rows deleted, %d emails deleted, %d password reset links deleted.\n",
        gmdate('Y-m-d H:i:s'),
        $result['enquiries_deleted'],
        $result['enquiry_metadata_cleared'],
        $result['audit_events_deleted'],
        $result['login_attempts_deleted'],
        $result['sessions_deleted'],
        $result['analytics_rows_deleted'],
        $result['emails_deleted'],
        $result['password_resets_deleted'],
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Retention purge failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
