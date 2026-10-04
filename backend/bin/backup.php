<?php

declare(strict_types=1);

/*
 * Nightly backup of the database and the media library (decision D-016):
 *
 *   php bin/backup.php
 *
 * Writes paxofi-database-<date>.sql.gz and paxofi-media-<date>.tar.gz to
 * BACKUP_PATH (outside the website folders) and deletes copies older than
 * BACKUP_KEEP_DAYS (default 14). A failure is emailed to ERROR_ALERT_TO.
 * Schedule nightly with cPanel → Cron Jobs (docs/RUNBOOKS.md, RB-18).
 */

use Paxofi\Core\Configuration\EnvLoader;
use Paxofi\Core\Configuration\Environment;
use Paxofi\Core\Logging\StreamLogger;
use Paxofi\CorporateWebsite\Bootstrap\ApiApplication;
use Paxofi\CorporateWebsite\Bootstrap\Settings;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Infrastructure\Backup\BackupRunner;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$environment = Environment::from((new EnvLoader())->load(dirname(__DIR__) . '/.env'));
$application = new ApiApplication(Settings::fromEnvironment($environment), new StreamLogger(fopen('php://stderr', 'wb')));

try {
    $path = trim($environment->get('BACKUP_PATH', '') ?? '');
    if ($path === '') {
        throw new RuntimeException('BACKUP_PATH is not set in backend/.env.');
    }
    $media = trim($environment->get('MEDIA_STORAGE_PATH', '') ?? '');
    $result = (new BackupRunner(
        Connection::make($environment),
        $path,
        $media === '' ? null : $media,
        max(1, $environment->integer('BACKUP_KEEP_DAYS', 14) ?? 14),
    ))->run(new DateTimeImmutable('now', new DateTimeZone('UTC')));
    fwrite(STDOUT, sprintf(
        "%s backup: %d tables in %s (%s); media %s; %d old copies deleted.\n",
        gmdate('Y-m-d H:i:s'),
        $result['tables'],
        basename($result['database']),
        number_format(filesize($result['database']) / 1024, 0) . ' KB',
        $result['media'] === null ? 'not configured' : basename($result['media']),
        $result['deleted'],
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Backup failed: ' . $exception->getMessage() . PHP_EOL);
    $application->errorAlerts()->report('The nightly backup failed', ['kind' => 'backup', 'error' => $exception->getMessage()]);
    $application->errorAlerts()->flush();
    exit(1);
}
