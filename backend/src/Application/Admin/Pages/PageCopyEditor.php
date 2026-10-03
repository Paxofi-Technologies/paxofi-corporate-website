<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\Pages;

use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Admin\Permission;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\Forbidden;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * Page text edited from the staff area (decision D-015), with the same steps
 * as products and services (D-011): saving changes only the draft; publishing
 * makes the draft the page's live text and keeps it as a version; any earlier
 * version can be restored as a draft.
 */
final class PageCopyEditor
{
    public function __construct(
        private readonly PageCopySchema $schema,
        private readonly PageCopyRepository $pages,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $overview = $this->pages->overview();

        return array_map(fn (string $key): array => [
            'page' => $key,
            'label' => $this->schema->page($key)['label'],
            'path' => $this->schema->page($key)['path'],
            'published_at' => $overview[$key]['published_at'] ?? null,
            'has_draft' => $overview[$key]['has_draft'] ?? false,
        ], $this->schema->pageKeys());
    }

    /** @return array<string, mixed> */
    public function get(string $page): array
    {
        $this->requirePage($page);
        $live = $this->pages->published($page);
        $draft = $this->pages->draft($page);

        return [
            'page' => $page,
            'label' => $this->schema->page($page)['label'],
            'path' => $this->schema->page($page)['path'],
            'fields' => $this->schema->page($page)['fields'],
            'live' => $live === null ? null : ['fields' => $this->schema->known($page, $live['data']), 'published_at' => $live['created_at']],
            'draft' => $draft === null ? null : ['fields' => $this->schema->known($page, $draft['data']), 'saved_at' => $draft['created_at'], 'author_name' => $draft['author_name']],
            'revisions' => $this->pages->history($page),
        ];
    }

    public function saveDraft(string $page, mixed $fields, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requirePage($page);
        $values = $this->schema->validate($page, $fields);
        $this->transactions->transaction(function () use ($page, $values, $staff, $context): void {
            $this->pages->saveDraft($page, $values, $staff->user->id);
            $this->record('page.draft_saved', $page, $staff, $context);
        });

        return $this->get($page);
    }

    public function discardDraft(string $page, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requirePage($page);
        if ($this->pages->draft($page) === null) {
            throw new Conflict('There is no draft to discard.');
        }
        $this->transactions->transaction(function () use ($page, $staff, $context): void {
            $this->pages->deleteDraft($page);
            $this->record('page.draft_discarded', $page, $staff, $context);
        });

        return $this->get($page);
    }

    /** Puts the draft live (needs content.publish). */
    public function publish(string $page, AuthenticatedStaff $staff, RequestContext $context): array
    {
        if (!$staff->user->can(Permission::CONTENT_PUBLISH)) {
            throw new Forbidden('An administrator publishes changes. Save your draft and ask them to publish it.');
        }
        $this->requirePage($page);
        $draft = $this->pages->draft($page) ?? throw new Conflict('There are no draft changes to publish.');
        // Re-checked against the current fields, in case the page changed since the draft was saved.
        $values = $this->schema->validate($page, $draft['data'] + $this->defaults($page));
        $this->transactions->transaction(function () use ($page, $values, $staff, $context): void {
            $this->pages->addPublished($page, $values, $staff->user->id);
            $this->pages->deleteDraft($page);
            $this->record('page.published', $page, $staff, $context);
        });

        return $this->get($page);
    }

    /** Copies an earlier published version into the draft. */
    public function restore(string $page, string $revisionId, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->requirePage($page);
        $data = $this->pages->revision($page, $revisionId) ?? throw new ResourceNotFound('Version not found.');
        $values = $this->schema->known($page, $data) + $this->defaults($page);
        $this->transactions->transaction(function () use ($page, $values, $staff, $context): void {
            $this->pages->saveDraft($page, $this->schema->validate($page, $values), $staff->user->id);
            $this->record('page.restored', $page, $staff, $context);
        });

        return $this->get($page);
    }

    /** The website's text for a page: the published version's fields (built-in wording fills the rest on the website). */
    public function live(string $page): array
    {
        $this->requirePage($page);
        $live = $this->pages->published($page);

        return ['page' => $page, 'fields' => $live === null ? (object) [] : (object) $this->schema->known($page, $live['data']), 'published_at' => $live['created_at'] ?? null];
    }

    /** @return array<string, string> */
    private function defaults(string $page): array
    {
        return array_column($this->schema->page($page)['fields'], 'default', 'key');
    }

    private function requirePage(string $page): void
    {
        if (!$this->schema->has($page)) {
            throw new ResourceNotFound('Page not found.');
        }
    }

    private function record(string $action, string $page, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $this->audit->record(new AuditEvent($action, AuditEvent::OUTCOME_SUCCESS, 'page', $page, $staff->user->id, $context->requestId));
    }
}
