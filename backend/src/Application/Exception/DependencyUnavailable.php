<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Exception;

use Throwable;

/**
 * A required backing service (database, mail, etc.) could not be reached.
 * The previous exception carries diagnostics for logs only.
 */
final class DependencyUnavailable extends ApplicationException
{
    public function __construct(string $message = 'The service is temporarily unavailable.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function errorCode(): string
    {
        return 'SERVICE_UNAVAILABLE';
    }
}
