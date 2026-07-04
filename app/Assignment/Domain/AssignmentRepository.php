<?php

declare(strict_types=1);

namespace App\Assignment\Domain;

use App\Candidature\Domain\ValueObject\CandidatureId;

/**
 * The domain's port for storing assignments. `save` upserts by candidature (one current
 * evaluator per candidature). The Eloquent implementation lives in Infrastructure.
 */
interface AssignmentRepository
{
    public function save(Assignment $assignment): void;

    public function findByCandidature(CandidatureId $candidatureId): ?Assignment;
}
