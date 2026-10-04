<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Mail;

use RuntimeException;

/** The mail server refused or could not be reached. The message is safe to log (no passwords). */
final class MailFailed extends RuntimeException
{
}
