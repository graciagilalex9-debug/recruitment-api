<?php

declare(strict_types=1);

namespace App\Assignment\Application\Lock;

use RuntimeException;

/**
 * Raised by a Mutex when the named lock is already held, i.e. the guarded operation is
 * already running elsewhere. Callers translate this into a domain-meaningful signal.
 */
final class LockNotAcquired extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct(sprintf('Could not acquire the lock "%s".', $name));
    }
}
