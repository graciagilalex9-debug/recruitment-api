<?php

declare(strict_types=1);

namespace App\Assignment\Application\Consolidated;

/**
 * One row of the consolidated listing (framework-agnostic primitives).
 */
final readonly class ConsolidatedRow
{
    public function __construct(
        public string $fullName,
        public string $email,
        public int $yearsOfExperience,
        public string $evaluatorName,
        public string $assignedAt,
        public int $evaluatorTotal,
        public string $evaluatorCandidateEmails,
    ) {}
}
