<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Assignment\Domain\PendingAssignmentReader;
use App\Candidature\Domain\Candidature;

final readonly class FakePendingAssignmentReader implements PendingAssignmentReader
{
    /**
     * @param  list<Candidature>  $candidatures
     * @param  array<string, int>  $loads
     */
    public function __construct(
        private array $candidatures = [],
        private array $loads = [],
    ) {}

    public function unassignedCandidatures(): array
    {
        return $this->candidatures;
    }

    public function evaluatorLoads(): array
    {
        return $this->loads;
    }
}
