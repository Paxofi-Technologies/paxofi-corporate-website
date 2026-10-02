<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Paxofi\CorporateWebsite\Application\Retention\RetentionPolicy;
use Paxofi\CorporateWebsite\Application\Retention\RetentionPurge;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

final class RetentionPurgeTest extends DatabaseTestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        self::$pdo->exec('DELETE FROM enquiries');
        self::$pdo->exec('DELETE FROM audit_events');
        $this->now = new DateTimeImmutable('2026-10-02 12:00:00', new DateTimeZone('UTC'));
    }

    public function testAppliesEachRetentionPeriod(): void
    {
        $fresh = $this->enquiry('-10 days');
        $ninetyOneDays = $this->enquiry('-91 days');
        $expired = $this->enquiry('-25 months');
        $this->auditEvent('-1 month');
        $this->auditEvent('-25 months');

        $result = (new RetentionPurge(self::$pdo, new RetentionPolicy()))->run($this->now);

        self::assertSame(['enquiry_metadata_cleared' => 2, 'enquiries_deleted' => 1, 'audit_events_deleted' => 1], $result);
        self::assertSame(['203.0.113.7', 'test-agent'], $this->network($fresh));
        self::assertSame([null, null], $this->network($ninetyOneDays));
        self::assertSame('0', (string) self::scalar('SELECT COUNT(*) FROM enquiries WHERE id = ?', [$expired]));
        self::assertSame('Ada', self::scalar('SELECT name FROM enquiries WHERE id = ?', [$ninetyOneDays]), 'enquiry content is kept');
        self::assertSame('1', (string) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'data_retention.purged'"));
    }

    public function testSecondRunChangesNothing(): void
    {
        $this->enquiry('-25 months');
        $purge = new RetentionPurge(self::$pdo, new RetentionPolicy());
        $purge->run($this->now);

        self::assertSame(['enquiry_metadata_cleared' => 0, 'enquiries_deleted' => 0, 'audit_events_deleted' => 0], $purge->run($this->now));
        self::assertSame('1', (string) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'data_retention.purged'"), 'no audit noise');
    }

    private function enquiry(string $age): string
    {
        $id = Uuid::v4();
        self::$pdo->prepare(
            "INSERT INTO enquiries (id, name, email, message, source_ip, user_agent, created_at)
             VALUES (?, 'Ada', 'ada@example.com', 'Hello', '203.0.113.7', 'test-agent', ?)",
        )->execute([$id, $this->now->modify($age)->format('Y-m-d H:i:s')]);

        return $id;
    }

    private function auditEvent(string $age): void
    {
        self::$pdo->prepare(
            "INSERT INTO audit_events (id, action, outcome, created_at) VALUES (?, 'enquiry.submitted', 'success', ?)",
        )->execute([Uuid::v4(), $this->now->modify($age)->format('Y-m-d H:i:s')]);
    }

    /** @return array{0: ?string, 1: ?string} */
    private function network(string $id): array
    {
        $statement = self::$pdo->prepare('SELECT source_ip, user_agent FROM enquiries WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return [$row['source_ip'], $row['user_agent']];
    }
}
