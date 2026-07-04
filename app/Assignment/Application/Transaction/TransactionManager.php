<?php

declare(strict_types=1);

namespace App\Assignment\Application\Transaction;

use Closure;

/**
 * Port for running work inside a database transaction: the callback either commits as a whole
 * or, if it throws, is rolled back entirely (all-or-nothing). The concrete implementation
 * (Laravel's `DB::transaction`) lives in Infrastructure, so the application layer stays
 * framework-agnostic — the use case just declares "do this atomically".
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function transactional(Closure $callback): mixed;
}
