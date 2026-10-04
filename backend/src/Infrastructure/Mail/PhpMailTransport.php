<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Infrastructure\Mail;

use Paxofi\CorporateWebsite\Application\Mail\Email;
use Paxofi\CorporateWebsite\Application\Mail\MailFailed;
use Paxofi\CorporateWebsite\Application\Mail\MailTransport;

/** The hosting account's own sendmail via PHP mail() (MAIL_TRANSPORT=mail): no password needed, weaker delivery than SMTP. */
final class PhpMailTransport implements MailTransport
{
    public function __construct(private readonly string $fromAddress, private readonly string $fromName)
    {
    }

    public function send(Email $email): void
    {
        $message = MessageFormatter::format($email, $this->fromAddress, $this->fromName);
        [$head, $body] = explode("\r\n\r\n", $message, 2);
        $headers = array_values(array_filter(explode("\r\n", $head), static fn (string $h): bool => !str_starts_with($h, 'To: ') && !str_starts_with($h, 'Subject: ')));
        preg_match('/^Subject: (.*)$/m', $head, $subject);
        if (!@mail(implode(', ', $email->to), trim($subject[1] ?? ''), $body, implode("\r\n", $headers), '-f' . $this->fromAddress)) {
            throw new MailFailed('The server did not accept the email (PHP mail()).');
        }
    }
}
