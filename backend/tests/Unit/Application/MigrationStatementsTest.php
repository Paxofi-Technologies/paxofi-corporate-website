<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Unit\Application;

use Paxofi\CorporateWebsite\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class MigrationStatementsTest extends TestCase
{
    public function testSplitsOnSemicolonsOutsideQuotesAndComments(): void
    {
        $sql = <<<'SQL'
            -- leading comment; with a semicolon
            CREATE TABLE a (id INT); /* block; comment */
            INSERT INTO a VALUES ('x;y'), ("it\'s; fine");
            SELECT `odd;name` FROM a
            SQL;

        self::assertSame([
            'CREATE TABLE a (id INT)',
            "INSERT INTO a VALUES ('x;y'), (\"it\\'s; fine\")",
            'SELECT `odd;name` FROM a',
        ], Migrator::statements($sql));
    }

    public function testEveryShippedMigrationParses(): void
    {
        foreach (glob(dirname(__DIR__, 4) . '/database/[0-9][0-9][0-9]_*.sql') ?: [] as $file) {
            self::assertNotEmpty(Migrator::statements((string) file_get_contents($file)), basename($file));
        }
    }
}
