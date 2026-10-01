<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Exception;

use RuntimeException;

/**
 * Base type for expected, client-safe application failures.
 *
 * The message of an ApplicationException is safe to show to API consumers;
 * the HTTP layer maps each subtype to a status code and stable error code.
 */
abstract class ApplicationException extends RuntimeException
{
    abstract public function errorCode(): string;

    /** @return array<string, mixed> */
    public function details(): array
    {
        return [];
    }
}
