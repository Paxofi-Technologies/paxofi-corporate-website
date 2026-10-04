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
        self::$pdo->exec('DELETE FROM login_attempts');
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

        self::assertSame(['enquiry_metadata_cleared' => 2, 'enquiries_deleted' => 1, 'audit_events_deleted' => 1, 'login_attempts_deleted' => 0, 'sessions_deleted' => 0, 'analytics_rows_deleted' => 0, 'emails_deleted' => 0, 'password_resets_deleted' => 0, 'applications_deleted' => 0, 'application_metadata_cleared' => 0, 'cv_uploads_deleted' => 0], $result);
        self::assertSame(['203.0.113.7', 'test-agent'], $this->network($fresh));
        self::assertSame([null, null], $this->network($ninetyOneDays));
        self::assertSame('0', (string) self::scalar('SELECT COUNT(*) FROM enquiries WHERE id = ?', [$expired]));
        self::assertSame('Ada', self::scalar('SELECT name FROM enquiries WHERE id = ?', [$ninetyOneDays]), 'enquiry content is kept');
        self::assertSame('1', (string) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'data_retention.purged'"));
    }

    public function testDeletesOldSignInAttemptsAndEndedSessions(): void
    {
        self::$pdo->exec("INSERT INTO users (id, email, status) VALUES ('00000000-0000-4000-8000-0000000000aa', 'retention@example.com', 'active')");
        $at = fn (string $age): string => $this->now->modify($age)->format('Y-m-d H:i:s');
        self::$pdo->prepare("INSERT INTO login_attempts (id, email, succeeded, created_at) VALUES (UUID(), 'a@example.com', 0, ?), (UUID(), 'a@example.com', 0, ?)")
            ->execute([$at('-91 days'), $at('-1 day')]);
        self::$pdo->prepare("INSERT INTO sessions (id, user_id, expires_at, last_seen_at, created_at) VALUES
            (REPEAT('a', 64), '00000000-0000-4000-8000-0000000000aa', ?, ?, ?),
            (REPEAT('b', 64), '00000000-0000-4000-8000-0000000000aa', ?, ?, ?)")
            ->execute([$at('-40 days'), $at('-40 days'), $at('-41 days'), $at('+1 hour'), $at('-1 minute'), $at('-1 hour')]);

        $result = (new RetentionPurge(self::$pdo, new RetentionPolicy()))->run($this->now);

        self::assertSame(1, $result['login_attempts_deleted']);
        self::assertSame(1, $result['sessions_deleted']);
        self::assertSame(str_repeat('b', 64), self::scalar('SELECT id FROM sessions'));
        self::$pdo->exec('DELETE FROM sessions');
        self::$pdo->exec("DELETE FROM users WHERE id = '00000000-0000-4000-8000-0000000000aa'");
    }

    public function testSecondRunChangesNothing(): void
    {
        $this->enquiry('-25 months');
        $purge = new RetentionPurge(self::$pdo, new RetentionPolicy());
        $purge->run($this->now);

        self::assertSame(['enquiry_metadata_cleared' => 0, 'enquiries_deleted' => 0, 'audit_events_deleted' => 0, 'login_attempts_deleted' => 0, 'sessions_deleted' => 0, 'analytics_rows_deleted' => 0, 'emails_deleted' => 0, 'password_resets_deleted' => 0, 'applications_deleted' => 0, 'application_metadata_cleared' => 0, 'cv_uploads_deleted' => 0], $purge->run($this->now));
        self::assertSame('1', (string) self::scalar("SELECT COUNT(*) FROM audit_events WHERE action = 'data_retention.purged'"), 'no audit noise');
    }

    public function testDeletesApplicationsAndTheirCvsAfterTwelveMonths(): void
    {
        $at = fn (string $age): string => $this->now->modify($age)->format('Y-m-d H:i:s');
        $role = '7e1f0c00-0000-4000-8000-000000000001';
        $insert = self::$pdo->prepare(
            "INSERT INTO job_applications (id, reference, opportunity_id, full_name, email, hours_per_week, motivation, experience, cv_reference, privacy_version, stage, stage_changed_at, closed_at, source_ip, user_agent, created_at)
             VALUES (?, ?, ?, 'Ada', 'ada@example.com', 20, 'Motivated.', 'Projects.', ?, '2026-10-04', ?, ?, ?, '203.0.113.7', 'test-agent', ?)",
        );
        // Closed 13 months ago: deleted. Closed 11 months ago (applied 14 months ago): kept. Open, no change for 13 months: deleted. Open and recent: kept.
        $insert->execute(['00000000-0000-4000-8000-0000000000c1', 'PIF-OLD001', $role, '00000000-0000-4000-8000-0000000000f1', 'rejected', $at('-13 months'), $at('-13 months'), $at('-14 months')]);
        $insert->execute(['00000000-0000-4000-8000-0000000000c2', 'PIF-KEEP01', $role, '00000000-0000-4000-8000-0000000000f2', 'declined', $at('-11 months'), $at('-11 months'), $at('-14 months')]);
        $insert->execute(['00000000-0000-4000-8000-0000000000c3', 'PIF-STALE1', $role, null, 'applied', $at('-13 months'), null, $at('-13 months')]);
        $insert->execute(['00000000-0000-4000-8000-0000000000c4', 'PIF-NEW001', $role, null, 'applied', $at('-2 days'), null, $at('-2 days')]);
        self::$pdo->prepare("INSERT INTO application_notes (id, application_id, kind, body) VALUES (UUID(), '00000000-0000-4000-8000-0000000000c1', 'note', 'Old note')")->execute();
        $upload = self::$pdo->prepare("INSERT INTO application_uploads (token_hash, file_reference, filename, media_type, size_bytes, created_at, claimed_at) VALUES (?, ?, 'CV.pdf', 'application/pdf', 100, ?, ?)");
        $upload->execute([str_repeat('1', 64), '00000000-0000-4000-8000-0000000000f3', $at('-2 days'), null]);
        $upload->execute([str_repeat('2', 64), '00000000-0000-4000-8000-0000000000f4', $at('-1 hour'), null]);
        $upload->execute([str_repeat('3', 64), '00000000-0000-4000-8000-0000000000f2', $at('-14 months'), $at('-14 months')]);
        $deleted = [];

        $result = (new RetentionPurge(self::$pdo, new RetentionPolicy(), static function (string $reference) use (&$deleted): void {
            $deleted[] = $reference;
        }))->run($this->now);

        self::assertSame(2, $result['applications_deleted']);
        self::assertSame(1, $result['application_metadata_cleared'], 'the kept application from 14 months ago; the recent one keeps its IP');
        self::assertSame(2, $result['cv_uploads_deleted'], 'one unclaimed upload older than a day, one claimed row');
        self::assertEqualsCanonicalizing(['00000000-0000-4000-8000-0000000000f1', '00000000-0000-4000-8000-0000000000f3'], $deleted, 'the CV of the deleted application and the unused upload; never a CV still in use');
        self::assertEqualsCanonicalizing(['PIF-KEEP01', 'PIF-NEW001'], self::$pdo->query('SELECT reference FROM job_applications')->fetchAll(\PDO::FETCH_COLUMN));
        self::assertSame('0', (string) self::scalar('SELECT COUNT(*) FROM application_notes'), 'notes go with the application');
        self::assertSame([null, null], array_values(self::$pdo->query("SELECT source_ip, user_agent FROM job_applications WHERE reference = 'PIF-KEEP01'")->fetch(\PDO::FETCH_NUM)));
        self::$pdo->exec('DELETE FROM job_applications');
        self::$pdo->exec('DELETE FROM application_uploads');
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
