<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Articles;

use Closure;
use DateTimeImmutable;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\Forbidden;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\Media\MediaKind;
use Paxofi\CorporateWebsite\Application\Media\MediaRepository;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * News & Insights articles in the staff area (D-021). Staff with content.edit
 * write and save drafts; an administrator (content.publish) publishes, hides
 * and deletes. Saving never changes what the website shows; publishing copies
 * the draft live. An article's address is set from its first title and never
 * changes, so shared links keep working.
 */
final class ArticleEditor
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const HIDDEN = 'hidden';

    /**
     * @param Closure(): DateTimeImmutable $clock
     * @param Closure(): string $uuid
     */
    public function __construct(
        private readonly Articles $articles,
        private readonly MediaRepository $media,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $clock,
        private readonly Closure $uuid,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return array_map(self::summary(...), $this->articles->all());
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        $row = $this->find($id);
        $draft = is_string($row['draft'] ?? null) ? json_decode($row['draft'], true) : null;

        return self::summary($row) + [
            'live' => $row['published_at'] === null ? null : ArticleContent::fromStored($row)->toArray(),
            'draft' => is_array($draft) ? ['content' => ArticleContent::fromStored($draft)->toArray(), 'saved_at' => $row['draft_saved_at'], 'author_name' => $row['draft_author_name'] ?? null] : null,
        ];
    }

    /** @param array<string, mixed> $input */
    public function create(array $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $content = $this->checkImage(ArticleContent::fromInput($input));
        $id = ($this->uuid)();
        $slug = $this->uniqueSlug($content->title);
        $this->transactions->transaction(function () use ($id, $slug, $content, $staff, $context): void {
            $this->articles->create($id, $slug, $content, $staff->user->id);
            $this->record('article.created', $id, $staff, $context);
        });

        return $this->get($id);
    }

    /** @param array<string, mixed> $input */
    public function saveDraft(string $id, array $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->find($id);
        $content = $this->checkImage(ArticleContent::fromInput($input));
        $this->transactions->transaction(function () use ($id, $content, $staff, $context): void {
            $this->articles->saveDraft($id, $content, $staff->user->id, ($this->clock)());
            $this->record('article.draft_saved', $id, $staff, $context);
        });

        return $this->get($id);
    }

    public function discardDraft(string $id, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $row = $this->find($id);
        if ($row['draft'] === null) {
            throw new Conflict('There is no draft to discard.');
        }
        if ($row['published_at'] === null) {
            throw new Conflict('This article has never been published, so its draft is all there is. Delete the article instead.');
        }
        $this->transactions->transaction(function () use ($id, $staff, $context): void {
            $this->articles->discardDraft($id);
            $this->record('article.draft_discarded', $id, $staff, $context);
        });

        return $this->get($id);
    }

    /** Puts the draft live and shows the article (content.publish). */
    public function publish(string $id, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requirePublisher($staff);
        $row = $this->find($id);
        $draft = is_string($row['draft'] ?? null) ? json_decode($row['draft'], true) : null;
        if (!is_array($draft)) {
            throw new Conflict('There are no draft changes to publish.');
        }
        $content = ArticleContent::fromStored($draft);
        $content = $content->imageId !== null && !$this->isImage($content->imageId) ? $content->withImage(null) : $content;
        $this->transactions->transaction(function () use ($id, $content, $staff, $context): void {
            $this->articles->publish($id, $content, ($this->clock)());
            $this->record('article.published', $id, $staff, $context);
        });

        return $this->get($id);
    }

    /** Hides a published article, or shows a hidden one again (content.publish). */
    public function setVisible(string $id, mixed $visible, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requirePublisher($staff);
        $row = $this->find($id);
        if (!is_bool($visible)) {
            throw new ValidationFailed(['visible' => 'Choose shown or hidden.'], 'Please choose shown or hidden.');
        }
        if ($row['published_at'] === null) {
            throw new Conflict('Publish the article first.');
        }
        $this->transactions->transaction(function () use ($id, $visible, $staff, $context): void {
            $this->articles->setState($id, $visible ? self::PUBLISHED : self::HIDDEN);
            $this->record($visible ? 'article.shown' : 'article.hidden', $id, $staff, $context);
        });

        return $this->get($id);
    }

    public function delete(string $id, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $this->requirePublisher($staff);
        $this->find($id);
        $this->transactions->transaction(function () use ($id, $staff, $context): void {
            $this->articles->delete($id);
            $this->record('article.deleted', $id, $staff, $context);
        });
    }

    private function checkImage(ArticleContent $content): ArticleContent
    {
        if ($content->imageId !== null && !$this->isImage($content->imageId)) {
            throw new ValidationFailed(['image_id' => 'That picture is no longer in the media library. Choose another.'], 'Please correct the highlighted fields.');
        }

        return $content;
    }

    private function isImage(string $id): bool
    {
        return ($this->media->find($id)['kind'] ?? null) === MediaKind::Image->value;
    }

    /** @return array<string, mixed> */
    private function find(string $id): array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? $this->articles->find($id) : null;

        return $row ?? throw new ResourceNotFound('Article not found.');
    }

    private function requirePublisher(AuthenticatedStaff $staff): void
    {
        if (!$staff->user->can(Permission::CONTENT_PUBLISH)) {
            throw new Forbidden('An administrator publishes articles. Save your draft and ask them to publish it.');
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title)), '-');
        $base = substr($base === '' ? 'article' : $base, 0, 150);
        $slug = $base;
        for ($n = 2; $this->articles->slugExists($slug); $n++) {
            $slug = $base . '-' . $n;
        }

        return $slug;
    }

    private function record(string $action, string $id, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $this->audit->record(new AuditEvent($action, AuditEvent::OUTCOME_SUCCESS, 'article', $id, $staff->user->id, $context->requestId));
    }

    /** @return array<string, mixed> */
    private static function summary(array $row): array
    {
        $draft = is_string($row['draft'] ?? null) ? json_decode($row['draft'], true) : null;

        return [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'path' => '/insights/' . $row['slug'],
            'title' => is_array($draft) && is_string($draft['title'] ?? null) && $row['published_at'] === null ? $draft['title'] : (string) $row['title'],
            'category' => (string) $row['category'],
            'state' => (string) $row['state'],
            'has_draft' => is_array($draft),
            'published_at' => $row['published_at'] === null ? null : (string) $row['published_at'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }
}
