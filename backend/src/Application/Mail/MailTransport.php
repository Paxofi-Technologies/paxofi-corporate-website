<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

/** Hands an email to a mail server (D-016). Throws MailFailed when it cannot. */
interface MailTransport
{
    public function send(Email $email): void;
}
