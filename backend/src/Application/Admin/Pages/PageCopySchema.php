<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin\Pages;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;
use RuntimeException;

/**
 * The editable text of each website page (decision D-015), from
 * config/page-copy.json. The website has an identical copy
 * (frontend/lib/page-copy.json; a test keeps them equal), so the API and the
 * pages agree on every field, its length limit and its built-in wording.
 *
 * Fields are plain text: one line ("line") or a short paragraph ("text"). No
 * markup is accepted; line breaks and control characters are removed.
 */
final class PageCopySchema
{
    /** @param array<string, array{label: string, path: string, fields: list<array{key: string, group: string, label: string, kind: string, max: int, default: string}>}> $pages */
    private function __construct(private readonly array $pages)
    {
    }

    public static function fromFile(string $path): self
    {
        $json = @file_get_contents($path);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) || !is_array($data['pages'] ?? null)) {
            throw new RuntimeException('The page text schema could not be read: ' . $path);
        }

        return new self($data['pages']);
    }

    public static function default(): self
    {
        return self::fromFile(dirname(__DIR__, 4) . '/config/page-copy.json');
    }

    /** @return list<string> */
    public function pageKeys(): array
    {
        return array_keys($this->pages);
    }

    public function has(string $page): bool
    {
        return isset($this->pages[$page]);
    }

    /** @return array{label: string, path: string, fields: list<array{key: string, group: string, label: string, kind: string, max: int, default: string}>} */
    public function page(string $page): array
    {
        return $this->pages[$page];
    }

    /**
     * Checks a submitted page: every field present, plain text, within its limit.
     *
     * @param mixed $input map of field key => text
     * @return array<string, string>
     */
    public function validate(string $page, mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $values = [];
        $errors = [];
        foreach ($this->page($page)['fields'] as $field) {
            $value = self::clean($input[$field['key']] ?? null);
            $length = mb_strlen($value);
            if ($length === 0) {
                $errors[$field['key']] = 'Enter some text.';
            } elseif ($length > $field['max']) {
                $errors[$field['key']] = "Use at most {$field['max']} characters.";
            }
            $values[$field['key']] = $value;
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        return $values;
    }

    /**
     * Stored text limited to the current fields: unknown keys dropped, missing
     * ones left out (the website then shows the built-in wording).
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function known(string $page, array $data): array
    {
        $values = [];
        foreach ($this->page($page)['fields'] as $field) {
            if (is_string($data[$field['key']] ?? null) && $data[$field['key']] !== '') {
                $values[$field['key']] = $data[$field['key']];
            }
        }

        return $values;
    }

    /** One line, trimmed, control characters removed. */
    private static function clean(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = mb_scrub($value, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', $value)));
    }
}
