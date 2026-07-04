<?php

declare(strict_types=1);

namespace App\Evaluator\Domain;

use App\Evaluator\Domain\ValueObject\EvaluatorId;
use App\Evaluator\Domain\ValueObject\EvaluatorName;
use DateTimeImmutable;

/**
 * Evaluator aggregate root — a person who reviews candidatures. Immutable, composed of
 * value objects, established through named constructors. Pure domain (no Laravel).
 */
final readonly class Evaluator
{
    private function __construct(
        private EvaluatorId $id,
        private EvaluatorName $name,
        private DateTimeImmutable $createdAt,
    ) {}

    public static function register(
        EvaluatorId $id,
        EvaluatorName $name,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $name, $createdAt);
    }

    public static function reconstitute(
        EvaluatorId $id,
        EvaluatorName $name,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $name, $createdAt);
    }

    public function id(): EvaluatorId
    {
        return $this->id;
    }

    public function name(): EvaluatorName
    {
        return $this->name;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
