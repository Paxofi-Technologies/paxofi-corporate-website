<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

use DateTimeImmutable;
use Paxofi\CorporateWebsite\Application\RequestContext;

/** job_applications, application_notes and application_uploads (migration 014, D-019). */
interface JobApplications
{
    /** @param array<string, mixed> $values validated by ApplicationInput */
    public function add(string $id, string $reference, string $roleId, array $values, ?array $cv, RequestContext $context): void;

    public function referenceExists(string $reference): bool;

    /** @return array{ip: int, email: int} applications in the last hour from the IP / last day from the email */
    public function recentCounts(?string $clientIp, string $email, DateTimeImmutable $now): array;

    public function hasOpenApplication(string $email, string $roleId): bool;

    public function addUpload(string $tokenHash, string $fileReference, string $filename, string $mediaType, int $size, ?string $clientIp): void;

    public function recentUploads(?string $clientIp, DateTimeImmutable $now): int;

    /** Claims an unclaimed upload from the last 24 hours. @return array{file_reference: string, filename: string, media_type: string, size_bytes: int}|null */
    public function claimUpload(string $tokenHash, DateTimeImmutable $now): ?array;

    /**
     * `viewer_id` and `viewer_email` leave out applications the viewer is recused from
     * or made themselves (D-029).
     *
     * @param array{stage?: string, role?: string, q?: string, viewer_id?: string, viewer_email?: string} $filters
     * @return array{items: list<array<string, mixed>>, total: int, counts: array<string, int>}
     */
    public function search(array $filters, int $page, int $perPage): array;

    /** @return array<string, mixed>|null */
    public function find(string $id): ?array;

    public function setStage(string $id, ApplicationStage $stage, DateTimeImmutable $now): void;

    /** @param array<string, int> $scores */
    public function setScores(string $id, string $gate, array $scores): void;

    public function addNote(string $applicationId, ?string $authorId, string $kind, string $body): void;

    /** @return list<array{id: string, kind: string, body: string, author_name: ?string, created_at: string}> */
    public function notes(string $applicationId): array;

    /** Whether the person wrote a scorecard entry on the application (D-029). */
    public function scoredBy(string $applicationId, string $userId): bool;

    /** Empties both scorecards, for example after a scorer recuses. */
    public function clearScores(string $id): void;

    public function delete(string $id): void;

    /**
     * Counts for the recruitment report (P3.1), for applications received
     * since the time given (all when null). No personal data.
     *
     * @return array{total: int, by_role: list<array{key: ?string, count: int}>, by_stage: list<array{key: ?string, count: int}>, by_source: list<array{key: ?string, count: int}>, by_campaign: list<array{key: ?string, count: int}>, by_day: list<array{key: ?string, count: int}>, review_times: list<array{created_at: string, first_reviewed_at: ?string}>}
     */
    public function report(?DateTimeImmutable $since): array;
}
