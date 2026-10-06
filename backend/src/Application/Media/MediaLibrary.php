<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

use Paxofi\Core\Contracts\TransactionManager;
use Paxofi\CorporateWebsite\Application\Admin\AuthenticatedStaff;
use Paxofi\CorporateWebsite\Application\Audit\AuditEvent;
use Paxofi\CorporateWebsite\Application\Audit\AuditRecorder;
use Paxofi\CorporateWebsite\Application\Exception\Conflict;
use Paxofi\CorporateWebsite\Application\Exception\DependencyUnavailable;
use Paxofi\CorporateWebsite\Application\Exception\ResourceNotFound;
use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use Paxofi\CorporateWebsite\Application\RequestContext;
use Throwable;

/**
 * The media library (decision D-012): images and documents uploaded by staff
 * for use on the website. Every file in it can be opened by anyone who has its
 * link, so it is for public material only.
 *
 * - Images are re-encoded on upload (metadata removed, size limited) and need
 *   a description for screen readers.
 * - Documents are stored as uploaded, after their type is checked, and are
 *   always downloaded rather than opened in the browser.
 * - A file used by a product, service, industry or article (live or draft)
 *   cannot be deleted, nor a document listed on the Resources page.
 * - An administrator lists documents on the public Resources page (D-023),
 *   each with a category and a short description.
 */
final class MediaLibrary
{
    /** Resources page categories (D-023), in display order; frontend lib/resources.ts keeps the same list. */
    public const RESOURCE_CATEGORIES = ['brochure' => 'Brochures', 'guide' => 'Guides', 'whitepaper' => 'Whitepapers', 'policy' => 'Policies', 'other' => 'Other documents'];

    public const NOT_CONFIGURED = 'File uploads are not set up on the server yet (MEDIA_STORAGE_PATH).';
    public const NO_IMAGE_PROCESSING = 'The server cannot process images (the PHP "gd" extension is off). Documents can still be uploaded.';

    /** @param \Closure(): string $newId */
    public function __construct(
        private readonly MediaRepository $media,
        private readonly MediaStorage $storage,
        private readonly ?ImageProcessor $images,
        private readonly AuditRecorder $audit,
        private readonly TransactionManager $transactions,
        private readonly \Closure $newId,
    ) {
    }

    /** @return array{uploads: bool, images: bool, image_max_bytes: int, document_max_bytes: int, server_max_bytes: ?int} */
    public function capabilities(?int $serverMaxBytes = null): array
    {
        return [
            'uploads' => $this->storage->available(),
            'images' => $this->images !== null,
            'image_max_bytes' => MediaRules::IMAGE_MAX_BYTES,
            'document_max_bytes' => MediaRules::DOCUMENT_MAX_BYTES,
            'server_max_bytes' => $serverMaxBytes,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function list(?MediaKind $kind): array
    {
        $usage = $this->media->usage();

        return array_map(static fn (array $row): array => self::present($row, $usage[(string) $row['id']] ?? []), $this->media->all($kind));
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return self::present($this->requireFile($id), $this->media->usage()[$id] ?? []);
    }

    /** @return array<string, mixed> the new file */
    public function upload(string $bytes, string $filename, array $details, AuthenticatedStaff $staff, RequestContext $context): array
    {
        if (!$this->storage->available()) {
            throw new DependencyUnavailable(self::NOT_CONFIGURED);
        }
        $detected = (new MediaInspector())->inspect($bytes, $filename);

        $width = null;
        $height = null;
        $altText = null;
        $title = null;
        if ($detected->kind === MediaKind::Image) {
            $altText = self::altText($details);
            if ($this->images === null) {
                throw new DependencyUnavailable(self::NO_IMAGE_PROCESSING);
            }
            $processed = $this->images->process($bytes, $detected->mimeType);
            $bytes = $processed->bytes;
            $width = $processed->width;
            $height = $processed->height;
        } else {
            $title = self::title($details, $filename);
        }

        $id = ($this->newId)();
        $this->storage->put($id, $bytes);
        try {
            $this->transactions->transaction(function () use ($id, $detected, $filename, $bytes, $width, $height, $altText, $title, $staff, $context): void {
                $this->media->insert([
                    'id' => $id,
                    'kind' => $detected->kind->value,
                    'filename' => MediaInspector::safeFilename($filename, $detected->extension),
                    'media_type' => $detected->mimeType,
                    'storage_reference' => $id,
                    'size_bytes' => strlen($bytes),
                    'width' => $width,
                    'height' => $height,
                    'alt_text' => $altText,
                    'title' => $title,
                    'sha256' => hash('sha256', $bytes),
                    'uploaded_by' => $staff->user->id,
                ]);
                $this->record('media.uploaded', $id, $staff, $context);
            });
        } catch (Throwable $exception) {
            $this->storage->delete($id);
            throw $exception;
        }

        return $this->get($id);
    }

    /** Changes an image's description or a document's title. */
    public function update(string $id, array $details, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $file = $this->requireFile($id);
        $isImage = $file['kind'] === MediaKind::Image->value;
        $altText = $isImage ? self::altText($details) : null;
        $title = $isImage ? null : self::title($details, (string) $file['filename'], required: true);
        $this->transactions->transaction(function () use ($id, $altText, $title, $staff, $context): void {
            $this->media->updateDetails($id, $altText, $title);
            $this->record('media.updated', $id, $staff, $context);
        });

        return $this->get($id);
    }

    /**
     * Lists a document on the Resources page or takes it off (needs content.publish, checked by the controller).
     *
     * @param array<string, mixed> $input {listed: bool, category: string, summary: string}
     * @return array<string, mixed>
     */
    public function setResource(string $id, array $input, AuthenticatedStaff $staff, RequestContext $context): array
    {
        $file = $this->requireFile($id);
        if ($file['kind'] !== MediaKind::Document->value) {
            throw new ValidationFailed(['listed' => 'Only documents can be listed on the Resources page.'], 'Only documents can be listed on the Resources page.');
        }
        $listed = $input['listed'] ?? null;
        if (!is_bool($listed)) {
            throw new ValidationFailed(['listed' => 'Choose listed or not listed.'], 'Please choose listed or not listed.');
        }
        $errors = [];
        $category = $input['category'] ?? null;
        if ($category === '' || $category === null) {
            $category = null;
        } elseif (!is_string($category) || !array_key_exists($category, self::RESOURCE_CATEGORIES)) {
            $errors['category'] = 'Choose one of the categories.';
            $category = null;
        }
        $summary = self::clean($input['summary'] ?? null);
        $length = mb_strlen($summary);
        if ($length > 300 || ($length > 0 && $length < 10)) {
            $errors['summary'] = 'Describe the document in 10 to 300 characters.';
        }
        if ($listed && $category === null && !isset($errors['category'])) {
            $errors['category'] = 'Choose a category for the Resources page.';
        }
        if ($listed && $summary === '' && !isset($errors['summary'])) {
            $errors['summary'] = 'Describe the document in 10 to 300 characters.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }
        $this->transactions->transaction(function () use ($id, $category, $summary, $listed, $staff, $context): void {
            $this->media->setResource($id, $category, $summary === '' ? null : $summary, $listed);
            $this->record($listed ? 'media.resource_listed' : 'media.resource_unlisted', $id, $staff, $context);
        });

        return $this->get($id);
    }

    /**
     * The public Resources page (D-023): listed documents with what the website needs to offer them.
     *
     * @return list<array<string, mixed>>
     */
    public function resources(): array
    {
        return array_map(static fn (array $row): array => [
            'title' => (string) $row['title'],
            'summary' => (string) ($row['resource_summary'] ?? ''),
            'category' => (string) $row['resource_category'],
            'category_label' => self::RESOURCE_CATEGORIES[(string) $row['resource_category']] ?? self::RESOURCE_CATEGORIES['other'],
            'format' => MediaRules::formatLabel((string) $row['media_type']),
            'size_bytes' => (int) ($row['size_bytes'] ?? 0),
            'path' => MediaPath::for((string) $row['id'], (string) $row['filename']),
            'listed_at' => str_replace(' ', 'T', (string) $row['resource_listed_at']) . 'Z',
        ], array_values(array_filter($this->media->listedResources(), static fn (array $row): bool => array_key_exists((string) $row['resource_category'], self::RESOURCE_CATEGORIES))));
    }

    public function delete(string $id, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $file = $this->requireFile($id);
        $usedBy = $this->media->usage()[$id] ?? [];
        if ($usedBy !== []) {
            throw new Conflict('This file is used by ' . implode(', ', $usedBy) . '. Remove it there first (and publish), then delete it.');
        }
        $this->transactions->transaction(function () use ($id, $staff, $context): void {
            $this->media->delete($id);
            $this->record('media.deleted', $id, $staff, $context);
        });
        $this->storage->delete((string) $file['storage_reference']);
    }

    /**
     * A file for the public download route.
     *
     * @return array{row: array<string, mixed>, bytes: string}
     */
    public function file(string $id): array
    {
        $row = $this->requireFile($id);
        $bytes = $this->storage->get((string) $row['storage_reference']) ?? throw new ResourceNotFound('File not found.');

        return ['row' => $row, 'bytes' => $bytes];
    }

    /** @return array<string, mixed> */
    private function requireFile(string $id): array
    {
        $row = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id) === 1 ? $this->media->find($id) : null;

        return $row ?? throw new ResourceNotFound('File not found.');
    }

    private function record(string $action, string $id, AuthenticatedStaff $staff, RequestContext $context): void
    {
        $this->audit->record(new AuditEvent($action, AuditEvent::OUTCOME_SUCCESS, 'media', $id, $staff->user->id, $context->requestId));
    }

    private static function altText(array $details): string
    {
        $alt = self::clean($details['alt_text'] ?? null);
        $length = mb_strlen($alt);
        if ($length < 2 || $length > 200) {
            throw new ValidationFailed(['alt_text' => 'Describe the picture in 2 to 200 characters (read aloud by screen readers).'], 'Please correct the highlighted fields.');
        }

        return $alt;
    }

    /** A document's link text; defaults to the file name when left empty on upload. */
    private static function title(array $details, string $filename, bool $required = false): string
    {
        $title = self::clean($details['title'] ?? null);
        if ($title === '' && !$required) {
            $title = self::clean(str_replace(['_', '-'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
        }
        $length = mb_strlen($title);
        if ($length < 2 || $length > 120) {
            throw new ValidationFailed(['title' => 'Enter a title of 2 to 120 characters (shown as the download link).'], 'Please correct the highlighted fields.');
        }

        return $title;
    }

    private static function clean(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = mb_scrub($value, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', $value)));
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $usedBy
     * @return array<string, mixed>
     */
    private static function present(array $row, array $usedBy): array
    {
        $id = (string) $row['id'];
        $filename = (string) $row['filename'];

        return [
            'id' => $id,
            'kind' => (string) $row['kind'],
            'filename' => $filename,
            'media_type' => (string) $row['media_type'],
            'format' => MediaRules::formatLabel((string) $row['media_type']),
            'size_bytes' => (int) ($row['size_bytes'] ?? 0),
            'width' => isset($row['width']) ? (int) $row['width'] : null,
            'height' => isset($row['height']) ? (int) $row['height'] : null,
            'alt_text' => $row['alt_text'] ?? null,
            'title' => $row['title'] ?? null,
            'path' => MediaPath::for($id, $filename),
            'uploaded_by' => $row['uploader_name'] ?? null,
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'used_by' => $usedBy,
            'resource' => [
                'listed' => ($row['resource_listed_at'] ?? null) !== null,
                'category' => $row['resource_category'] ?? null,
                'summary' => $row['resource_summary'] ?? null,
                'listed_at' => isset($row['resource_listed_at']) ? (string) $row['resource_listed_at'] : null,
            ],
        ];
    }
}
