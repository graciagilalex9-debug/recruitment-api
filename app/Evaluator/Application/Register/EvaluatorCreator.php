<?php

declare(strict_types=1);

namespace App\Evaluator\Application\Register;

use App\Evaluator\Domain\Evaluator;
use App\Evaluator\Domain\EvaluatorRepository;
use App\Evaluator\Domain\ValueObject\EvaluatorName;
use DateTimeImmutable;

/**
 * Use case: create a new evaluator. Turns primitives into value objects, persists via the
 * repository port, and returns a primitive DTO.
 */
final readonly class EvaluatorCreator
{
    public function __construct(
        private EvaluatorRepository $repository,
    ) {}

    public function create(RegisterEvaluatorCommand $command): EvaluatorResponse
    {
        $evaluator = Evaluator::register(
            $this->repository->nextIdentity(),
            new EvaluatorName($command->name),
            new DateTimeImmutable,
        );

        $this->repository->save($evaluator);

        return EvaluatorResponse::fromEvaluator($evaluator);
    }
}
