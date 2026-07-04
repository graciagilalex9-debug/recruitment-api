<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Persistence;

use App\Assignment\Domain\Assignment;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Evaluator\Domain\ValueObject\EvaluatorId;
use DateTimeImmutable;

/**
 * Translates between the Assignment aggregate and its Eloquent model.
 */
final class AssignmentMapper
{
    public function toDomain(AssignmentModel $model): Assignment
    {
        return Assignment::reconstitute(
            new CandidatureId($model->candidature_id),
            new EvaluatorId($model->evaluator_id),
            DateTimeImmutable::createFromInterface($model->assigned_at),
        );
    }
}
