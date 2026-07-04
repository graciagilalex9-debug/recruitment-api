<?php

declare(strict_types=1);

namespace App\Assignment\Application\Lock;

use Closure;

/**
 * Mutual-exclusion port: run a callback while holding an exclusive named lock, so an
 * operation executes one at a time. The concrete lock (Redis) lives in Infrastructure; the
 * application only knows "run this under the lock, or fail if it's already held".
 */
interface Mutex
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws LockNotAcquired if the named lock is already held by someone else.
     */
    public function withLock(string $name, Closure $callback): mixed;
}
