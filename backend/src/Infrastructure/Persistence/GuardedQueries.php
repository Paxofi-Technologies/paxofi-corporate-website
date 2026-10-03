<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\Core\Persistence\Exception\PersistenceException;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;

/** Reads and writes through Database, reporting persistence failures as a dependency outage (503). */
trait GuardedQueries
{
    abstract private function database(): Database;

    /**
     * @param array<string, mixed> $parameters
     * @return list<array<string, mixed>>
     */
    private function select(string $sql, array $parameters = []): array
    {
        try {
            return array_values($this->database()->reader()->fetchAll($sql, $parameters));
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable(previous: $exception);
        }
    }

    /** @param array<string, mixed> $parameters */
    private function write(string $sql, array $parameters = []): int
    {
        try {
            return $this->database()->writer()->execute($sql, $parameters);
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable(previous: $exception);
        }
    }
}
