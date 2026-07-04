<?php

declare(strict_types=1);

namespace App\Assignment\Application\Assign;

use App\Assignment\Domain\Assignment;
use DateTimeInterface;

/**
 * Output DTO for the assign use case: framework-agnostic primitives.
 */
final readonly class AssignmentResponse
{
    public function __construct(
        public string $candidatureId,
        public string $evaluatorId,
        public string $assignedAt,
    ) {}

    public static function fromAssignment(Assignment $assignment): self
    {
        return new self(
            $assignment->candidatureId()->value(),
            $assignment->evaluatorId()->value(),
            $assignment->assignedAt()->format(DateTimeInterface::ATOM),
        );
    }
}
