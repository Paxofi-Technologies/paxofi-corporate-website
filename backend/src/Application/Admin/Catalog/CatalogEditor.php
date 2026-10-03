<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\Catalog;

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
 * Products and services edited from the staff area (decision D-011).
 *
 * Each item has its live content (what the website shows while the item is
 * visible) and at most one draft. Saving changes only the draft; publishing
 * copies it to the live content and keeps it as a revision, so any earlier
 * version can be restored as a new draft. New items start hidden.
 *
 * An item may show a picture and offer a document from the media library
 * (D-012); both are part of the content, so they follow the same draft and
 * publish steps.
 */
final class CatalogEditor
{
    public function __construct(
        private readonly CatalogEditorRepository $items,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly MediaRepository $media,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(CatalogKind $kind): array
    {
        return array_map(self::summary(...), $this->items->all($kind));
    }

    /** @return array<string, mixed> */
    public function get(CatalogKind $kind, string $id): array
    {
        $item = $this->requireItem($kind, $id);
        $draft = $this->items->draft($kind, $id);

        return [
            'item' => self::summary($item) + ['content' => CatalogContent::fromStored($item)->toArray()],
            'draft' => $draft === null ? null : [
                'content' => CatalogContent::fromStored($draft['data'])->toArray(),
                'saved_at' => $draft['created_at'],
                'author_name' => $draft['author_name'],
            ],
            'revisions' => array_map(static fn (array $revision): array => [
                'id' => $revision['id'],
                'state' => $revision['state'],
                'created_at' => $revision['created_at'],
                'author_name' => $revision['author_name'],
                'content' => CatalogContent::fromStored($revision['data'])->toArray(),
            ], $this->items->revisions($kind, $id)),
        ];
    }

    /** Creates a hidden item; it appears on the website once shown. */
    public function create(CatalogKind $kind, array $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $content = $this->checkMedia(CatalogContent::fromInput($input));
        $slug = $this->uniqueSlug($kind, $content->name);
        $id = (string) $this->transactions->transaction(function () use ($kind, $slug, $content, $staff, $context): string {
            $id = $this->items->insert($kind, $slug, $content);
            $this->items->addRevision($kind, $id, 'created', $content, $staff->user->id);
            $this->record('catalog.created', $kind, $id, $staff, $context);

            return $id;
        });

        return $this->get($kind, $id);
    }

    public function saveDraft(CatalogKind $kind, string $id, array $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requireItem($kind, $id);
        $content = $this->checkMedia(CatalogContent::fromInput($input));
        $this->transactions->transaction(function () use ($kind, $id, $content, $staff, $context): void {
            $this->items->saveDraft($kind, $id, $content, $staff->user->id);
            $this->record('catalog.draft_saved', $kind, $id, $staff, $context);
        });

        return $this->get($kind, $id);
    }

    public function discardDraft(CatalogKind $kind, string $id, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requireItem($kind, $id);
        if ($this->items->draft($kind, $id) === null) {
            throw new Conflict('There is no draft to discard.');
        }
        $this->transactions->transaction(function () use ($kind, $id, $staff, $context): void {
            $this->items->deleteDraft($kind, $id);
            $this->record('catalog.draft_discarded', $kind, $id, $staff, $context);
        });

        return $this->get($kind, $id);
    }

    /** Puts the draft live (needs content.publish). */
    public function publish(CatalogKind $kind, string $id, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requirePublisher($staff);
        $this->requireItem($kind, $id);
        $draft = $this->items->draft($kind, $id) ?? throw new Conflict('There are no draft changes to publish.');
        $content = $this->withoutDeletedMedia(CatalogContent::fromStored($draft['data']));
        $this->transactions->transaction(function () use ($kind, $id, $content, $staff, $context): void {
            $this->items->updateLive($kind, $id, $content);
            $this->items->markDraftPublished($kind, $id, $staff->user->id);
            $this->record('catalog.published', $kind, $id, $staff, $context);
        });

        return $this->get($kind, $id);
    }

    /** Shows or hides the item on the website (needs content.publish). */
    public function setVisible(CatalogKind $kind, string $id, mixed $visible, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requirePublisher($staff);
        $this->requireItem($kind, $id);
        if (!is_bool($visible)) {
            throw new ValidationFailed(['visible' => 'Choose shown or hidden.'], 'Please choose shown or hidden.');
        }
        $this->transactions->transaction(function () use ($kind, $id, $visible, $staff, $context): void {
            $this->items->setVisible($kind, $id, $visible);
            $this->record($visible ? 'catalog.shown' : 'catalog.hidden', $kind, $id, $staff, $context);
        });

        return $this->get($kind, $id);
    }

    /** Copies an earlier version into the draft, ready to review and publish. */
    public function restore(CatalogKind $kind, string $id, string $revisionId, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requireItem($kind, $id);
        $revision = $this->items->revision($kind, $id, $revisionId) ?? throw new ResourceNotFound('Version not found.');
        $content = $this->withoutDeletedMedia(CatalogContent::fromStored($revision['data']));
        $this->transactions->transaction(function () use ($kind, $id, $content, $staff, $context): void {
            $this->items->saveDraft($kind, $id, $content, $staff->user->id);
            $this->record('catalog.restored', $kind, $id, $staff, $context);
        });

        return $this->get($kind, $id);
    }

    /** The chosen picture and document must exist in the media library, as an image and a document. */
    private function checkMedia(CatalogContent $content): CatalogContent
    {
        $errors = [];
        if ($content->imageId !== null && !$this->isMedia($content->imageId, MediaKind::Image)) {
            $errors['image_id'] = 'That image is no longer in the media library. Choose another.';
        }
        if ($content->documentId !== null && !$this->isMedia($content->documentId, MediaKind::Document)) {
            $errors['document_id'] = 'That document is no longer in the media library. Choose another.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        return $content;
    }

    /** An older version may name a file deleted since; it is left out rather than blocking the restore. */
    private function withoutDeletedMedia(CatalogContent $content): CatalogContent
    {
        return $content->withMedia(
            $content->imageId !== null && $this->isMedia($content->imageId, MediaKind::Image) ? $content->imageId : null,
            $content->documentId !== null && $this->isMedia($content->documentId, MediaKind::Document) ? $content->documentId : null,
        );
    }

    private function isMedia(string $id, MediaKind $kind): bool
    {
        return ($this->media->find($id)['kind'] ?? null) === $kind->value;
    }

    /** @return array<string, mixed> */
    private function requireItem(CatalogKind $kind, string $id): array
    {
        $item = preg_match('/^[0-9a-f-]{36}$/i', $id) === 1 ? $this->items->find($kind, $id) : null;

        return $item ?? throw new ResourceNotFound('Item not found.');
    }

    private function requirePublisher(AuthenticatedStaff $staff): void
    {
        if (!$staff->user->can(Permission::CONTENT_PUBLISH)) {
            throw new Forbidden('An administrator publishes changes. Save your draft and ask them to publish it.');
        }
    }

    private function uniqueSlug(CatalogKind $kind, string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name)) ?? '', '-');
        $base = substr($base === '' ? $kind->singular() : $base, 0, 160);
        $slug = $base;
        for ($n = 2; $this->items->slugExists($kind, $slug); $n++) {
            $slug = $base . '-' . $n;
        }

        return $slug;
    }

    private function record(string $action, CatalogKind $kind, string $id, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $this->audit->record(new AuditEvent($action, AuditEvent::OUTCOME_SUCCESS, $kind->singular(), $id, $staff->user->id, $context->requestId));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function summary(array $row): array
    {
        $visible = ($row['lifecycle_state'] ?? '') === 'published' && ($row['published_at'] ?? null) !== null;

        return [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'visible' => $visible,
            'has_draft' => (bool) ($row['has_draft'] ?? false),
            'sort_order' => (int) ($row['sort_order'] ?? 100),
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        ];
    }
}
