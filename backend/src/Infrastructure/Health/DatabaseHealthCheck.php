<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Health;

use Paxofi\Core\Contracts\HealthCheck;
use Paxofi\Core\Contracts\Logger;
use Paxofi\Core\Observability\HealthResult;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\Database;
use Throwable;

final class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(private readonly Database $database, private readonly Logger $logger)
    {
    }

    public function name(): string
    {
        return 'database';
    }

    public function check(): HealthResult
    {
        try {
            $this->database->reader()->fetchAll('SELECT 1 AS ok');

            return HealthResult::healthy();
        } catch (Throwable $exception) {
            $this->logger->warning('readiness.database_unavailable', [
                'exception' => $exception::class,
                'cause' => $exception->getPrevious()?->getMessage() ?? $exception->getMessage(),
            ]);

            return HealthResult::unhealthy('Database unavailable.');
        }
    }
}
