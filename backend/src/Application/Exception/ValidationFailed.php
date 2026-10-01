<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Exception;

final class ValidationFailed extends ApplicationException
{
    /** @param array<string, string> $fieldErrors field name => client-safe message */
    public function __construct(
        private readonly array $fieldErrors,
        string $message = 'One or more fields are invalid.',
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'VALIDATION_ERROR';
    }

    /** @return array<string, string> */
    public function fieldErrors(): array
    {
        return $this->fieldErrors;
    }

    public function details(): array
    {
        return $this->fieldErrors === [] ? [] : ['fields' => $this->fieldErrors];
    }
}
