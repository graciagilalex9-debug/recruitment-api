<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Assignment\Application\Transaction\TransactionManager;
use Closure;

/**
 * Test double for the TransactionManager port: no real transaction, just runs the callback.
 * A fake (a working implementation), not a mock — for pure unit tests with no DB.
 */
final class ImmediateTransactionManager implements TransactionManager
{
    public function transactional(Closure $callback): mixed
    {
        return $callback();
    }
}
