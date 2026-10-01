<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Exception;

final class RateLimited extends ApplicationException
{
    public function __construct(private readonly int $retryAfterSeconds)
    {
        parent::__construct('Too many requests. Please try again later.');
    }

    public function errorCode(): string
    {
        return 'RATE_LIMITED';
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
