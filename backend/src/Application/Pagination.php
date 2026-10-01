<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

final readonly class Pagination
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 50;

    public function __construct(public int $page = 1, public int $perPage = self::DEFAULT_PER_PAGE)
    {
    }

    /** @param array<string, mixed> $query */
    public static function fromQuery(array $query): self
    {
        $errors = [];
        $page = self::positiveInt($query['page'] ?? null, 1, PHP_INT_MAX, 'page', $errors);
        $perPage = self::positiveInt($query['per_page'] ?? null, self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE, 'per_page', $errors);

        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Invalid pagination parameters.');
        }

        return new self($page, $perPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** @return array{page: int, per_page: int, total: int, total_pages: int} */
    public function meta(int $total): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->perPage,
            'total' => $total,
            'total_pages' => (int) ceil($total / $this->perPage),
        ];
    }

    /** @param array<string, string> $errors */
    private static function positiveInt(mixed $value, int $default, int $max, string $field, array &$errors): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $int = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;
        if ($int === false || $int < 1 || $int > $max) {
            $errors[$field] = sprintf('Must be an integer between 1 and %d.', $max);

            return $default;
        }

        return $int;
    }
}
