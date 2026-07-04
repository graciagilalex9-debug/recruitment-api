<?php

declare(strict_types=1);

namespace App\Evaluator\Infrastructure\Persistence;

use App\Evaluator\Domain\Evaluator;
use App\Evaluator\Domain\EvaluatorRepository;
use App\Evaluator\Domain\ValueObject\EvaluatorId;
use Illuminate\Support\Str;

final readonly class EloquentEvaluatorRepository implements EvaluatorRepository
{
    public function __construct(
        private EvaluatorMapper $mapper,
    ) {}

    public function nextIdentity(): EvaluatorId
    {
        return new EvaluatorId((string) Str::ulid());
    }

    public function findById(EvaluatorId $id): ?Evaluator
    {
        $model = EvaluatorModel::find($id->value());

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function save(Evaluator $evaluator): void
    {
        EvaluatorModel::query()->create($this->mapper->toRow($evaluator));
    }
}
