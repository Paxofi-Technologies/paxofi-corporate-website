<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Contact;

final readonly class EnquiryReceipt
{
    public function __construct(public string $formKey, public bool $persisted)
    {
    }
}
