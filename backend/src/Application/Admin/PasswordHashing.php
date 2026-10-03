<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Admin;

use Paxofi\Core\Contracts\PasswordHasher;

/** PCF password hasher plus rehash detection when the preferred algorithm changes. */
interface PasswordHashing extends PasswordHasher
{
    public function needsRehash(string $hash): bool;
}
