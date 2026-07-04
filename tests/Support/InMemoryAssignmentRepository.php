<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Assignment\Domain\Assignment;
use App\Assignment\Domain\AssignmentRepository;
use App\Candidature\Domain\ValueObject\CandidatureId;

final class InMemoryAssignmentRepository implements AssignmentRepository
{
    /** @var array<string, Assignment> keyed by candidature id (one current assignment each) */
    private array $byCandidature = [];

    public function save(Assignment $assignment): void
    {
        $this->byCandidature[$assignment->candidatureId()->value()] = $assignment;
    }

    public function findByCandidature(CandidatureId $candidatureId): ?Assignment
    {
        return $this->byCandidature[$candidatureId->value()] ?? null;
    }

    public function count(): int
    {
        return count($this->byCandidature);
    }
}
