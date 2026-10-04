<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Support;

use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailFailed;
use Paxofi\CorporateWebsite\Application\Mail\MailTransport;

/** Keeps sent emails in memory; can be told to fail. */
final class MemoryMailTransport implements MailTransport
{
    /** @var list<Email> */
    public array $sent = [];
    public bool $failing = false;

    public function send(Email $email): void
    {
        if ($this->failing) {
            throw new MailFailed('Mail server replied 451 to MAIL: try again later');
        }
        $this->sent[] = $email;
    }
}
