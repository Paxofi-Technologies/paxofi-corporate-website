<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use Closure;

/**
 * A transport decided only when an email is actually sent (D-031), so the
 * settings saved in the staff area are read from the database only then.
 */
final class LazyMailTransport implements MailTransport
{
    /** @param Closure(): ?MailTransport $resolve */
    public function __construct(private readonly Closure $resolve)
    {
    }

    public function send(Email $email): void
    {
        $transport = ($this->resolve)();
        if ($transport === null) {
            throw new MailFailed('Email sending is not configured.');
        }
        $transport->send($email);
    }
}
