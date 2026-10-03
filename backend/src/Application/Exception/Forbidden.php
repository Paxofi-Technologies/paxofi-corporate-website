<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Exception;

/** Signed in, but not allowed to perform this action (403). */
final class Forbidden extends ApplicationException
{
    /** @param string $code a more specific error code, e.g. MFA_REQUIRED */
    public function __construct(string $message = 'You do not have permission to do this.', private readonly string $specificCode = 'FORBIDDEN')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->specificCode;
    }
}
