<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Exception;

/** No valid staff session, or sign-in credentials were rejected (401). */
final class Unauthenticated extends ApplicationException
{
    public function __construct(string $message = 'Please sign in.')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'UNAUTHENTICATED';
    }
}
