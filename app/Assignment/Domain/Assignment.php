<?php

declare(strict_types=1);

namespace App\Assignment\Domain;

use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Evaluator\Domain\ValueObject\EvaluatorId;
use DateTimeImmutable;

/**
 * Links a candidature to the evaluator responsible for it, with the assignment date.
 * Identified by its candidature (one current evaluator per candidature). Composed of the
 * two contexts' id value objects; pure domain (no Laravel).
 */
final readonly class Assignment
{
    private function __construct(
        private CandidatureId $candidatureId,
        private EvaluatorId $evaluatorId,
        private DateTimeImmutable $assignedAt,
    ) {}

    public static function assign(
        CandidatureId $candidatureId,
        EvaluatorId $evaluatorId,
        DateTimeImmutable $assignedAt,
    ): self {
        return new self($candidatureId, $evaluatorId, $assignedAt);
    }

    public static function reconstitute(
        CandidatureId $candidatureId,
        EvaluatorId $evaluatorId,
        DateTimeImmutable $assignedAt,
    ): self {
        return new self($candidatureId, $evaluatorId, $assignedAt);
    }

    public function candidatureId(): CandidatureId
    {
        return $this->candidatureId;
    }

    public function evaluatorId(): EvaluatorId
    {
        return $this->evaluatorId;
    }

    public function assignedAt(): DateTimeImmutable
    {
        return $this->assignedAt;
    }
}
