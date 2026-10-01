<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Closure;
use PDO;
use Paxofi\Core\Contracts\Connection as ConnectionContract;
use Paxofi\Core\Contracts\Repository;
use Paxofi\Core\Contracts\TransactionManager as TransactionManagerContract;
use Paxofi\Core\Persistence\PdoConnection;
use Paxofi\Core\Persistence\PdoRepository;
use Paxofi\Core\Persistence\TransactionManager;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Throwable;

/**
 * Lazily opens one PDO handle per request and exposes it through the PCF
 * persistence contracts (Repository for reads, Connection for writes,
 * TransactionManager for atomic units of work).
 *
 * Endpoints that never touch the database (health, navigation) never connect.
 */
final class Database
{
    private ?PDO $pdo = null;
    private ?PdoRepository $reader = null;
    private ?PdoConnection $writer = null;
    private ?TransactionManager $transactions = null;

    /** @param Closure(): PDO $connect */
    public function __construct(private readonly Closure $connect)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            try {
                $this->pdo = ($this->connect)();
            } catch (Throwable $exception) {
                throw new DependencyUnavailable(previous: $exception);
            }
        }

        return $this->pdo;
    }

    public function reader(): Repository
    {
        return $this->reader ??= new PdoRepository($this->pdo());
    }

    public function writer(): ConnectionContract
    {
        return $this->writer ??= new PdoConnection($this->pdo());
    }

    public function transactions(): TransactionManagerContract
    {
        return $this->transactions ??= new TransactionManager($this->writer());
    }
}
