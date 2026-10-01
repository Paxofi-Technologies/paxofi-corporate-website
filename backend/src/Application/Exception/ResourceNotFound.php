<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Exception;

final class ResourceNotFound extends ApplicationException
{
    public function __construct(string $message = 'Resource not found.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'NOT_FOUND';
    }
}
