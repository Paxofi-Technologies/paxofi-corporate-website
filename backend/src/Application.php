<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite;

/**
 * Minimal application boundary until the approved PCF integration package and
 * bootstrap contract are consumed by the Corporate Website application.
 */
final class Application
{
    public function name(): string
    {
        return 'Paxofi Corporate Website';
    }
}
