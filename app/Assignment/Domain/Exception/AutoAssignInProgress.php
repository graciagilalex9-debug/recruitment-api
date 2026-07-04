<?php

declare(strict_types=1);

namespace App\Assignment\Domain\Exception;

use DomainException;

/**
 * Raised when a bulk auto-assignment is requested while another one is already running.
 * A business conflict, mapped to HTTP 409.
 */
final class AutoAssignInProgress extends DomainException
{
    public function __construct()
    {
        parent::__construct('An auto-assignment is already in progress.');
    }
}
