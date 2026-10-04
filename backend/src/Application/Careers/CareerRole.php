<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/** A role shown on careers.paxofi.com (table career_opportunities, D-018). */
final readonly class CareerRole
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const CLOSED = 'closed';

    /** @param array{responsibilities: list<string>, deliverables: list<string>, competencies: list<string>, tools: list<string>, evidence: string, assessment: string, interview: string} $content */
    public function __construct(
        public string $id,
        public string $slug,
        public string $code,
        public string $title,
        public string $family,
        public string $summary,
        public string $purpose,
        public array $content,
        public string $state,
        public int $sortOrder,
        public ?string $publishedAt,
        public ?string $updatedAt,
    ) {
    }

    /** @return array<string, mixed> what the careers site shows */
    public function toPublicArray(): array
    {
        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'title' => $this->title,
            'family' => $this->family,
            'summary' => $this->summary,
            'purpose' => $this->purpose,
        ] + $this->content;
    }

    /** @return array<string, mixed> */
    public function toAdminArray(): array
    {
        return ['id' => $this->id, 'state' => $this->state, 'sort_order' => $this->sortOrder, 'published_at' => $this->publishedAt, 'updated_at' => $this->updatedAt] + $this->toPublicArray();
    }
}
