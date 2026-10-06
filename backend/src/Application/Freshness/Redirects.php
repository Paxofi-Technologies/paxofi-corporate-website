<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Freshness;

use Closure;
use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;

/**
 * Redirects for retired or renamed pages (D-025, SRS 14.24). The website sends
 * a visitor who opens "from" on to "to" with a permanent redirect (301), so old
 * links and search results keep working. A redirect never hides a live page,
 * and never points at another redirect.
 */
final class Redirects
{
    /** @param Closure(): string $uuid */
    public function __construct(
        private readonly RedirectStore $store,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly Closure $uuid,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $rows = $this->store->all();
        usort($rows, static fn (array $a, array $b): int => strcmp($a['from_path'], $b['from_path']));

        return array_map(self::present(...), $rows);
    }

    /** @return list<array{from: string, to: string}> what the website applies */
    public function map(): array
    {
        return array_map(static fn (array $row): array => ['from' => $row['from_path'], 'to' => $row['to_path']], $this->store->all());
    }

    /** @return array<string, mixed> */
    public function create(mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        if (count($this->store->all()) >= RedirectRules::MAX_REDIRECTS) {
            throw new Conflict('The website can hold up to ' . RedirectRules::MAX_REDIRECTS . ' redirects. Delete ones that are no longer needed first.');
        }
        [$from, $to, $note] = $this->validate($input, null);
        $id = ($this->uuid)();
        $this->transactions->transaction(function () use ($id, $from, $to, $note, $staff, $context): void {
            $this->store->create($id, $from, $to, $note, $staff->user->id);
            $this->audit->record(new AuditEvent('redirect.created', AuditEvent::OUTCOME_SUCCESS, 'redirect', $id, $staff->user->id, $context->requestId));
        });

        return self::present($this->find($id));
    }

    /** @return array<string, mixed> */
    public function update(string $id, mixed $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $this->find($id);
        [$from, $to, $note] = $this->validate($input, $id);
        $this->transactions->transaction(function () use ($id, $from, $to, $note, $staff, $context): void {
            $this->store->update($id, $from, $to, $note);
            $this->audit->record(new AuditEvent('redirect.updated', AuditEvent::OUTCOME_SUCCESS, 'redirect', $id, $staff->user->id, $context->requestId));
        });

        return self::present($this->find($id));
    }

    public function delete(string $id, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $this->find($id);
        $this->transactions->transaction(function () use ($id, $staff, $context): void {
            $this->store->delete($id);
            $this->audit->record(new AuditEvent('redirect.deleted', AuditEvent::OUTCOME_SUCCESS, 'redirect', $id, $staff->user->id, $context->requestId));
        });
    }

    /** @return array{0: string, 1: string, 2: string|null} */
    private function validate(mixed $input, ?string $id): array
    {
        if (!is_array($input)) {
            throw new ValidationFailed([], 'Send the redirect as a JSON object.');
        }
        $errors = [];
        $from = RedirectRules::normalizeFrom($input['from'] ?? null);
        $to = RedirectRules::validTarget($input['to'] ?? null);
        if ($from === null) {
            $errors['from'] = 'Enter the old address on this website, starting with /, for example /insights/old-article.';
        } elseif (RedirectRules::isReserved($from)) {
            $errors['from'] = 'That address is a page the website needs. Choose the address of a page that no longer exists.';
        } elseif (in_array($from, $this->store->livePaths(), true)) {
            $errors['from'] = 'That page is live on the website. Hide or retire it first, then add the redirect.';
        }
        if ($to === null) {
            $errors['to'] = 'Enter an address on this website starting with /, or a full link starting with https://.';
        }
        $note = $input['note'] ?? null;
        if ($note !== null && (!is_string($note) || mb_strlen(trim($note)) > 200)) {
            $errors['note'] = 'Keep the note to 200 characters.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        /** @var string $from */
        /** @var string $to */
        if (RedirectRules::targetPath($to) === $from) {
            throw new ValidationFailed(['to' => 'A page cannot redirect to itself.'], 'Please correct the highlighted fields.');
        }

        foreach ($this->store->all() as $other) {
            if ($other['id'] === $id) {
                continue;
            }
            if ($other['from_path'] === $from) {
                throw new Conflict("There is already a redirect from {$from}. Change that one instead.");
            }
            if (RedirectRules::targetPath($to) === $other['from_path']) {
                throw new ValidationFailed(['to' => "{$other['from_path']} itself redirects to {$other['to_path']}. Point straight there instead."], 'Please correct the highlighted fields.');
            }
            if (RedirectRules::targetPath($other['to_path']) === $from) {
                throw new ValidationFailed(['from' => "The redirect from {$other['from_path']} points to this address. Change it to the new address first, so visitors are not sent twice."], 'Please correct the highlighted fields.');
            }
        }
        $note = is_string($note) ? trim((string) preg_replace('/\s+/u', ' ', $note)) : null;

        return [$from, $to, $note === '' ? null : $note];
    }

    /** @return array{id: string, from_path: string, to_path: string, note: string|null, created_by: string|null, created_at: string, updated_at: string} */
    private function find(string $id): array
    {
        $row = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? $this->store->find($id) : null;

        return $row ?? throw new ResourceNotFound('Redirect not found.');
    }

    /** @return array<string, mixed> */
    private static function present(array $row): array
    {
        return [
            'id' => $row['id'],
            'from' => $row['from_path'],
            'to' => $row['to_path'],
            'note' => $row['note'],
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
}
