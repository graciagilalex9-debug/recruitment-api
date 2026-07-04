<?php

declare(strict_types=1);

namespace App\Evaluator\Application\Register;

use App\Evaluator\Domain\Evaluator;
use DateTimeInterface;

/**
 * Output DTO for the evaluator use cases: framework-agnostic primitives.
 */
final readonly class EvaluatorResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $createdAt,
    ) {}

    public static function fromEvaluator(Evaluator $evaluator): self
    {
        return new self(
            $evaluator->id()->value(),
            $evaluator->name()->value(),
            $evaluator->createdAt()->format(DateTimeInterface::ATOM),
        );
    }
}
