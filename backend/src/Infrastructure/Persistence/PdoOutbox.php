<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\Outbox;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/** email_outbox (migration 013, D-016). */
final class PdoOutbox implements Outbox
{
    use GuardedQueries;

    /** Emails added through this instance (the API sends them right after the response). */
    private int $added = 0;

    public function __construct(private readonly Database $database)
    {
    }

    private function database(): Database
    {
        return $this->database;
    }

    public function add(Email $email): string
    {
        $id = Uuid::v4();
        $this->write(
            'INSERT INTO email_outbox (id, kind, recipients, reply_to, subject, body_text) VALUES (:id, :kind, :to, :reply, :subject, :body)',
            ['id' => $id, 'kind' => mb_substr($email->kind, 0, 40), 'to' => implode(',', $email->to), 'reply' => $email->replyTo, 'subject' => $email->subject, 'body' => $email->text],
        );

        $this->added++;

        return $id;
    }

    public function addedCount(): int
    {
        return $this->added;
    }

    public function due(DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->select(
            sprintf("SELECT id, kind, recipients, reply_to, subject, body_text, attempts FROM email_outbox WHERE status = 'pending' AND next_attempt_at <= :now ORDER BY created_at, id LIMIT %d", max(1, min(100, $limit))),
            ['now' => $now->format('Y-m-d H:i:s')],
        );

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'attempts' => (int) $row['attempts'],
            'email' => new Email(explode(',', (string) $row['recipients']), (string) $row['subject'], (string) $row['body_text'], $row['reply_to'] === null ? null : (string) $row['reply_to'], (string) $row['kind']),
        ], $rows);
    }

    public function markSent(string $id, DateTimeImmutable $now): void
    {
        $this->write("UPDATE email_outbox SET status = 'sent', sent_at = :now, attempts = attempts + 1, last_error = NULL WHERE id = :id", ['id' => $id, 'now' => $now->format('Y-m-d H:i:s')]);
    }

    public function markFailed(string $id, string $error, ?DateTimeImmutable $nextAttempt): void
    {
        $this->write(
            'UPDATE email_outbox SET attempts = attempts + 1, last_error = :error, status = :status, next_attempt_at = COALESCE(:next, next_attempt_at) WHERE id = :id',
            ['id' => $id, 'error' => mb_substr($error, 0, 500), 'status' => $nextAttempt === null ? 'failed' : 'pending', 'next' => $nextAttempt?->format('Y-m-d H:i:s')],
        );
    }

    public function failedSince(DateTimeImmutable $since): int
    {
        return (int) ($this->select("SELECT COUNT(*) AS n FROM email_outbox WHERE status = 'failed' AND created_at >= :since", ['since' => $since->format('Y-m-d H:i:s')])[0]['n'] ?? 0);
    }
}
