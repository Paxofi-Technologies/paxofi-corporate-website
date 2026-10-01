<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application;

/** Transport-neutral metadata about the caller of a use case. */
final readonly class RequestContext
{
    public function __construct(
        public string $requestId,
        public ?string $clientIp = null,
        public ?string $userAgent = null,
    ) {
    }
}
