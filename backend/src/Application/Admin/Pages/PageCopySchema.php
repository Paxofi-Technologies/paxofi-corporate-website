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
 * markup is accepted; line breaks and control characters are removed. The menu
 * and footer also have "link" fields (a page on this site such as /about, or
 * an https:// address) and an "email" field. An "optional" field may be left
 * empty, which hides it; a "pair" (a link's name and address) must be filled
 * in together or left empty together.
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
            $values[$field['key']] = $value;
            $error = self::check($field, $value);
            if ($error !== null) {
                $errors[$field['key']] = $error;
            }
        }
        foreach ($this->page($page)['fields'] as $field) {
            $other = $field['pair'] ?? null;
            if (is_string($other) && !isset($errors[$field['key']]) && $values[$field['key']] === '' && ($values[$other] ?? '') !== '') {
                $errors[$field['key']] = 'Fill in both the name and the link, or leave both empty.';
            }
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
            $value = $data[$field['key']] ?? null;
            // An optional field published empty is kept: it hides that link on the website.
            if (is_string($value) && ($value !== '' || ($field['optional'] ?? false))) {
                $values[$field['key']] = $value;
            }
        }

        return $values;
    }

    /** Why a field's value is not acceptable, or null. */
    private static function check(array $field, string $value): ?string
    {
        if ($value === '') {
            return ($field['optional'] ?? false) ? null : 'Enter some text.';
        }
        if (mb_strlen($value) > $field['max']) {
            return "Use at most {$field['max']} characters.";
        }

        return match ($field['kind']) {
            'link' => self::isLink($value) ? null : 'Use a page on this site such as /about, or a full address starting with https://.',
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? null : 'Enter an email address such as hello@paxofi.com.',
            default => null,
        };
    }

    /** A path on this site (not //host) or an https:// address, with no spaces or quotes. */
    public static function isLink(string $value): bool
    {
        if (preg_match('{^/(?!/)[A-Za-z0-9\-._~/?#=&%+]*$}', $value) === 1) {
            return true;
        }
        if (!str_starts_with($value, 'https://') || preg_match('/[\s"\'<>\\\\]/', $value) === 1) {
            return false;
        }
        $host = parse_url($value, PHP_URL_HOST);

        return is_string($host) && preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/i', $host) === 1;
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
