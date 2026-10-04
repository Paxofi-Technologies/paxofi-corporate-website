<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Persistence;

use Closure;
use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\Mail\Attachment;
use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\Outbox;
use Paxofi\CorporateWebsite\Infrastructure\Support\Uuid;

/** email_outbox (migration 013, D-016). */
final class PdoOutbox implements Outbox
{
    use GuardedQueries;

    /** Emails added through this instance (the API sends them right after the response). */
    private int $added = 0;

    /** @param (Closure(): DateTimeImmutable)|null $clock the application's clock; the database's own time when null */
    public function __construct(private readonly Database $database, private readonly ?Closure $clock = null)
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
            'INSERT INTO email_outbox (id, kind, recipients, reply_to, subject, body_text, created_at, next_attempt_at)
             VALUES (:id, :kind, :to, :reply, :subject, :body, COALESCE(:now1, CURRENT_TIMESTAMP), COALESCE(:now2, CURRENT_TIMESTAMP))',
            ['id' => $id, 'kind' => mb_substr($email->kind, 0, 40), 'to' => implode(',', $email->to), 'reply' => $email->replyTo, 'subject' => $email->subject, 'body' => $email->text, 'now1' => $now = $this->now(), 'now2' => $now],
        );

        foreach ($email->attachments as $attachment) {
            $this->write(
                'INSERT INTO email_attachments (id, email_id, filename, media_type, content) VALUES (:id, :email, :name, :type, :content)',
                ['id' => Uuid::v4(), 'email' => $id, 'name' => $attachment->filename, 'type' => $attachment->mediaType, 'content' => $attachment->content],
            );
        }

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

        return array_map(fn (array $row): array => [
            'id' => (string) $row['id'],
            'attempts' => (int) $row['attempts'],
            'email' => new Email(explode(',', (string) $row['recipients']), (string) $row['subject'], (string) $row['body_text'], $row['reply_to'] === null ? null : (string) $row['reply_to'], (string) $row['kind'], $this->attachments((string) $row['id'])),
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

    /** @return list<Attachment> */
    private function attachments(string $emailId): array
    {
        return array_map(
            static fn (array $row): Attachment => new Attachment((string) $row['filename'], (string) $row['media_type'], (string) $row['content']),
            $this->select('SELECT filename, media_type, content FROM email_attachments WHERE email_id = :id ORDER BY created_at, id', ['id' => $emailId]),
        );
    }

    /** Queued "now" on the same clock that later decides what is due. */
    private function now(): ?string
    {
        return $this->clock === null ? null : ($this->clock)()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
