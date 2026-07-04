<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Persistence;

use App\Assignment\Domain\Assignment;
use App\Assignment\Domain\AssignmentRepository;
use App\Candidature\Domain\ValueObject\CandidatureId;

final readonly class EloquentAssignmentRepository implements AssignmentRepository
{
    public function __construct(
        private AssignmentMapper $mapper,
    ) {}

    /**
     * Upsert by candidature: one current evaluator per candidature. Reassigning updates the
     * existing row. The unique index on candidature_id is the race-safe guard.
     */
    public function save(Assignment $assignment): void
    {
        AssignmentModel::query()->updateOrCreate(
            ['candidature_id' => $assignment->candidatureId()->value()],
            [
                'evaluator_id' => $assignment->evaluatorId()->value(),
                'assigned_at' => $assignment->assignedAt()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function findByCandidature(CandidatureId $candidatureId): ?Assignment
    {
        $model = AssignmentModel::query()
            ->where('candidature_id', $candidatureId->value())
            ->first();

        return $model === null ? null : $this->mapper->toDomain($model);
    }
}
