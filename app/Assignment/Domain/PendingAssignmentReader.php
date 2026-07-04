<?php

declare(strict_types=1);

namespace App\Assignment\Domain;

use App\Candidature\Domain\Candidature;

/**
 * Read side for bulk auto-assignment. Returns the candidatures with no assignment (as
 * aggregates, so the domain validator can decide eligibility) and each evaluator's current
 * assignment count (including evaluators with zero).
 */
interface PendingAssignmentReader
{
    /**
     * @return list<Candidature>
     */
    public function unassignedCandidatures(): array;

    /**
     * @return array<string, int> evaluatorId => current assignment count
     */
    public function evaluatorLoads(): array;
}
