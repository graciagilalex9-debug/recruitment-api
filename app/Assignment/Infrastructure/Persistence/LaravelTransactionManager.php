<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Persistence;

use App\Assignment\Application\Transaction\TransactionManager;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Laravel implementation of the TransactionManager port. `DB::transaction` commits when the
 * callback returns and rolls back every write if it throws, returning the callback's value.
 */
final readonly class LaravelTransactionManager implements TransactionManager
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transactional(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
