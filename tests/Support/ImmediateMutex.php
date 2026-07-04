<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Assignment\Application\Lock\Mutex;
use Closure;

/**
 * Test double for the Mutex port: the lock is always free, so it just runs the callback.
 * A real fake (a working implementation), not a mock — no expectations, no framework.
 */
final class ImmediateMutex implements Mutex
{
    public function withLock(string $name, Closure $callback): mixed
    {
        return $callback();
    }
}
