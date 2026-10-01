<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Tests\Support;

use Paxofi\Core\Contracts\TransactionManager;

final class ImmediateTransactionManager implements TransactionManager
{
    public int $transactions = 0;

    public function transaction(callable $operation): mixed
    {
        $this->transactions++;

        return $operation();
    }
}
