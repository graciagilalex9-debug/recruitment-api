<?php

declare(strict_types=1);

namespace App\Assignment\Application\AutoAssign;

/**
 * Summary of a bulk auto-assignment run.
 */
final readonly class AutoAssignmentResponse
{
    public function __construct(
        public int $assigned,
        public int $skippedIneligible,
    ) {}
}
