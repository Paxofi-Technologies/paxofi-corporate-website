<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Exception;

/** The request conflicts with the current state, e.g. a duplicate email (409). */
final class Conflict extends ApplicationException
{
    public function errorCode(): string
    {
        return 'CONFLICT';
    }
}
