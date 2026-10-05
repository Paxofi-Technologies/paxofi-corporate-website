<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\Catalog;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

/**
 * The editable content of one product, service or industry, validated. Plain
 * text only: the website escapes it, and no markup is accepted.
 *
 * Some fields belong to one kind only (D-022): status to products, description
 * and related items to industries. CatalogEditor clears them for other kinds.
 */
final readonly class CatalogContent
{
    /** Icons the website can draw (frontend/lib/catalog.ts keeps the same list). */
    public const ICONS = [
        'shield-check', 'cog', 'code', 'network', 'rocket', 'workflow', 'cloud', 'megaphone', 'layers', 'globe', 'lightbulb', 'briefcase', 'smartphone', 'database', 'lock',
        'banknote', 'graduation-cap', 'heart-pulse', 'shopping-cart', 'truck', 'landmark', 'sprout', 'factory', 'hand-heart', 'store',
    ];
    public const MAX_POINTS = 5;
    /** Product maturity (SRS 14.7), shown as a label on the product. */
    public const STATUSES = ['planned', 'in_development', 'pilot', 'beta', 'available', 'limited', 'paused', 'retired'];
    public const DESCRIPTION_MAX = 1500;
    public const MAX_RELATED = 6;

    /** @param list<string> $points */
    public function __construct(
        public string $name,
        public ?string $label,
        public string $icon,
        public string $summary,
        public array $points,
        public int $sortOrder,
        public ?string $imageId = null,
        public ?string $documentId = null,
        public ?string $status = null,
        public ?string $description = null,
        /** @var list<string> "product:<slug>" or "service:<slug>" */
        public array $related = [],
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $errors = [];
        $name = self::text($input, 'name', 2, 80, $errors);
        $label = self::text($input, 'label', 0, 40, $errors);
        $summary = self::text($input, 'summary', 10, 300, $errors);

        $icon = is_string($input['icon'] ?? null) ? $input['icon'] : '';
        if (!in_array($icon, self::ICONS, true)) {
            $errors['icon'] = 'Choose one of the icons.';
        }

        $points = [];
        $raw = $input['points'] ?? [];
        if (!is_array($raw) || count($raw) > self::MAX_POINTS) {
            $errors['points'] = 'Use at most ' . self::MAX_POINTS . ' points.';
        } else {
            foreach ($raw as $point) {
                $point = is_string($point) ? self::clean($point) : '';
                if ($point === '') {
                    continue;
                }
                if (mb_strlen($point) > 80) {
                    $errors['points'] = 'Keep each point to 80 characters or fewer.';
                }
                $points[] = $point;
            }
        }

        $order = $input['sort_order'] ?? 100;
        if (!is_int($order) && !(is_string($order) && ctype_digit($order))) {
            $errors['sort_order'] = 'Enter a whole number from 0 to 999.';
            $order = 100;
        }
        $order = (int) $order;
        if ($order < 0 || $order > 999) {
            $errors['sort_order'] = 'Enter a whole number from 0 to 999.';
        }

        $imageId = self::mediaId($input, 'image_id', 'Choose an image from the media library.', $errors);
        $documentId = self::mediaId($input, 'document_id', 'Choose a document from the media library.', $errors);

        $status = $input['status'] ?? null;
        if ($status === '') {
            $status = null;
        }
        if ($status !== null && !in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Choose one of the statuses.';
            $status = null;
        }

        $description = self::paragraphs($input['description'] ?? null);
        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            $errors['description'] = 'Use at most ' . self::DESCRIPTION_MAX . ' characters.';
        }

        $related = [];
        $raw = $input['related'] ?? [];
        if (!is_array($raw) || count($raw) > self::MAX_RELATED) {
            $errors['related'] = 'Choose at most ' . self::MAX_RELATED . ' related products and services.';
        } else {
            foreach ($raw as $reference) {
                if (!is_string($reference) || preg_match('/^(product|service):[a-z0-9-]{1,180}$/', $reference) !== 1) {
                    $errors['related'] = 'Choose related items from the list.';
                    break;
                }
                if (!in_array($reference, $related, true)) {
                    $related[] = $reference;
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        return new self($name, $label === '' ? null : $label, $icon, $summary, $points, $order, $imageId, $documentId, $status, $description === '' ? null : $description, $related);
    }

    /** @param array<string, mixed> $data */
    public static function fromStored(array $data): self
    {
        return new self(
            (string) ($data['name'] ?? ''),
            isset($data['label']) && $data['label'] !== '' ? (string) $data['label'] : null,
            (string) ($data['icon'] ?? 'layers'),
            (string) ($data['summary'] ?? ''),
            array_values(array_filter(is_array($data['points'] ?? null) ? $data['points'] : [], 'is_string')),
            (int) ($data['sort_order'] ?? 100),
            self::storedId($data['image_id'] ?? null),
            self::storedId($data['document_id'] ?? null),
            in_array($data['status'] ?? null, self::STATUSES, true) ? $data['status'] : null,
            is_string($data['description'] ?? null) && $data['description'] !== '' ? $data['description'] : null,
            array_values(array_filter(is_array($data['related'] ?? null) ? $data['related'] : [], static fn (mixed $r): bool => is_string($r) && preg_match('/^(product|service):[a-z0-9-]{1,180}$/', $r) === 1)),
        );
    }

    /** The same content with a different picture or document (null removes it). */
    public function withMedia(?string $imageId, ?string $documentId): self
    {
        return new self($this->name, $this->label, $this->icon, $this->summary, $this->points, $this->sortOrder, $imageId, $documentId, $this->status, $this->description, $this->related);
    }

    /** Only the fields that belong to this kind (D-022): status for products, description and related items for industries. */
    public function forKind(CatalogKind $kind): self
    {
        return new self(
            $this->name,
            $this->label,
            $this->icon,
            $this->summary,
            $this->points,
            $this->sortOrder,
            $this->imageId,
            $this->documentId,
            $kind === CatalogKind::Products ? $this->status : null,
            $kind === CatalogKind::Industries ? $this->description : null,
            $kind === CatalogKind::Industries ? $this->related : [],
        );
    }

    /** The same content with a different list of related items. */
    public function withRelated(array $related): self
    {
        return new self($this->name, $this->label, $this->icon, $this->summary, $this->points, $this->sortOrder, $this->imageId, $this->documentId, $this->status, $this->description, array_values($related));
    }

    /** @return array{name: string, label: ?string, icon: string, summary: string, points: list<string>, sort_order: int, image_id: ?string, document_id: ?string, status: ?string, description: ?string, related: list<string>} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'icon' => $this->icon,
            'summary' => $this->summary,
            'points' => $this->points,
            'sort_order' => $this->sortOrder,
            'image_id' => $this->imageId,
            'document_id' => $this->documentId,
            'status' => $this->status,
            'description' => $this->description,
            'related' => $this->related,
        ];
    }

    /** @param array<string, string> $errors */
    private static function mediaId(array $input, string $field, string $message, array &$errors): ?string
    {
        $value = $input[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || self::storedId($value) === null) {
            $errors[$field] = $message;

            return null;
        }

        return $value;
    }

    private static function storedId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $value) === 1 ? $value : null;
    }

    /** @param array<string, string> $errors */
    private static function text(array $input, string $field, int $min, int $max, array &$errors): string
    {
        $value = is_string($input[$field] ?? null) ? self::clean($input[$field]) : '';
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            $errors[$field] = $min === 0 ? "Use at most {$max} characters." : "Enter {$min} to {$max} characters.";
        }

        return $value;
    }

    /** Several paragraphs: lines trimmed, at most one blank line between paragraphs, other control characters removed. */
    private static function paragraphs(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = str_replace(["\r\n", "\r"], "\n", mb_scrub($value, 'UTF-8'));
        $value = preg_replace('/[^\P{Cc}\n]|\p{Cf}/u', ' ', $value) ?? '';
        $lines = array_map(static fn (string $line): string => trim(preg_replace('/[ \t]+/u', ' ', $line) ?? ''), explode("\n", $value));

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)) ?? '');
    }

    /** Single line, trimmed, control characters removed. */
    private static function clean(string $value): string
    {
        $value = mb_scrub($value, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', $value) ?? '') ?? '');
    }
}
