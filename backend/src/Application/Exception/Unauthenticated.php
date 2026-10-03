<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Exception;

/** No valid staff session, or sign-in credentials were rejected (401). */
final class Unauthenticated extends ApplicationException
{
    /** @param string $code a more specific error code, e.g. MFA_REQUIRED */
    public function __construct(string $message = 'Please sign in.', private readonly string $specificCode = 'UNAUTHENTICATED')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->specificCode;
    }
}
