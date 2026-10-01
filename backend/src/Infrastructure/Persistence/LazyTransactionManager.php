<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\Core\Persistence\Exception\PersistenceException;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;

/**
 * Defers opening the database until a transaction actually starts, and
 * reports begin/commit/rollback failures as a dependency outage (503).
 */
final class LazyTransactionManager implements TransactionManager
{
    public function __construct(private readonly Database $database)
    {
    }

    public function transaction(callable $operation): mixed
    {
        try {
            return $this->database->transactions()->transaction($operation);
        } catch (PersistenceException $exception) {
            throw new DependencyUnavailable(previous: $exception);
        }
    }
}
