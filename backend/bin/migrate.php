<?php

declare(strict_types=1);

/*
 * Database migration runner.
 *
 *   php bin/migrate.php --status          list applied / pending migrations
 *   php bin/migrate.php                   apply pending migrations
 *   php bin/migrate.php --baseline=003    adopt an existing database once:
 *                                         record 001–003 as already applied
 *
 * Reads DB_* settings from the environment or backend/.env.
 */

use Paxofi\Core\Configuration\EnvLoader;
use Paxofi\Core\Configuration\Environment;
use Paxofi\CorporateWebsite\Database\Connection;
use Paxofi\CorporateWebsite\Database\Migrator;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['status', 'baseline:']);

try {
    $environment = Environment::from((new EnvLoader())->load(dirname(__DIR__) . '/.env'));
    $migrator = new Migrator(Connection::make($environment), dirname(__DIR__, 2) . '/database');

    if (isset($options['baseline'])) {
        foreach ($migrator->baseline((string) $options['baseline']) as $version) {
            fwrite(STDOUT, "baselined  {$version}\n");
        }
        fwrite(STDOUT, "Baseline recorded. Run again without --baseline to apply the rest.\n");
        exit(0);
    }

    foreach ($migrator->modified() as $version) {
        fwrite(STDERR, "WARNING: {$version} was changed after it was applied. Add a new migration instead of editing old ones.\n");
    }

    if (isset($options['status'])) {
        $applied = $migrator->applied();
        foreach (array_keys($migrator->available()) as $version) {
            $state = isset($applied[$version]) ? 'applied   ' . $applied[$version]['applied_at'] : 'PENDING';
            fwrite(STDOUT, sprintf("%-50s %s\n", $version, $state));
        }
        exit(0);
    }

    $done = $migrator->migrate();
    foreach ($done as $version) {
        fwrite(STDOUT, "applied    {$version}\n");
    }
    fwrite(STDOUT, $done === [] ? "Nothing to migrate; database is up to date.\n" : sprintf("%d migration(s) applied.\n", count($done)));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
