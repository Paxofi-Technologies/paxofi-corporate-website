<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Paxofi\Core\Contracts\TransactionManager;

/** Defers opening the database until a transaction actually starts. */
final class LazyTransactionManager implements TransactionManager
{
    public function __construct(private readonly Database $database)
    {
    }

    public function transaction(callable $operation): mixed
    {
        return $this->database->transactions()->transaction($operation);
    }
}
