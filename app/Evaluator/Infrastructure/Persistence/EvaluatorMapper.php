<?php

declare(strict_types=1);

namespace App\Evaluator\Infrastructure\Persistence;

use App\Evaluator\Domain\Evaluator;
use App\Evaluator\Domain\ValueObject\EvaluatorId;
use App\Evaluator\Domain\ValueObject\EvaluatorName;
use DateTimeImmutable;

/**
 * Translates between the Evaluator aggregate and its Eloquent model / DB row.
 */
final class EvaluatorMapper
{
    public function toDomain(EvaluatorModel $model): Evaluator
    {
        return Evaluator::reconstitute(
            new EvaluatorId($model->id),
            new EvaluatorName($model->name),
            DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(Evaluator $evaluator): array
    {
        return [
            'id' => $evaluator->id()->value(),
            'name' => $evaluator->name()->value(),
            'created_at' => $evaluator->createdAt()->format('Y-m-d H:i:s'),
        ];
    }
}
