<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application;

use PDO;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\Database;
use Paxofi\CorporateWebsite\Infrastructure\Persistence\LazyTransactionManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LazyTransactionManagerTest extends TestCase
{
    public function testCommitFailureIsReportedAsDependencyOutage(): void
    {
        $pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $transactions = new LazyTransactionManager(new Database(static fn (): PDO => $pdo));

        $this->expectException(DependencyUnavailable::class);
        // Ending the transaction inside the operation makes PCF's commit fail,
        // as a dropped connection or deadlock at COMMIT would.
        $transactions->transaction(static fn () => $pdo->commit());
    }

    public function testOperationExceptionsPassThroughUnchanged(): void
    {
        $transactions = new LazyTransactionManager(new Database(static fn (): PDO => new PDO('sqlite::memory:')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('business rule');
        $transactions->transaction(static fn () => throw new RuntimeException('business rule'));
    }

    public function testConnectionIsOpenedOnlyWhenATransactionStarts(): void
    {
        $opened = 0;
        new LazyTransactionManager(new Database(static function () use (&$opened): PDO {
            $opened++;

            return new PDO('sqlite::memory:');
        }));

        self::assertSame(0, $opened);
    }
}
