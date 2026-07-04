<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Evaluator\Domain\Evaluator;
use App\Evaluator\Domain\EvaluatorRepository;
use App\Evaluator\Domain\ValueObject\EvaluatorId;
use Illuminate\Support\Str;

final class InMemoryEvaluatorRepository implements EvaluatorRepository
{
    /** @var array<string, Evaluator> keyed by id */
    private array $byId = [];

    public function nextIdentity(): EvaluatorId
    {
        return new EvaluatorId((string) Str::ulid());
    }

    public function findById(EvaluatorId $id): ?Evaluator
    {
        return $this->byId[$id->value()] ?? null;
    }

    public function save(Evaluator $evaluator): void
    {
        $this->byId[$evaluator->id()->value()] = $evaluator;
    }
}
