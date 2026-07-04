<?php

declare(strict_types=1);

namespace App\Candidature\Application\Summary;

/**
 * Consolidated summary of one candidature (framework-agnostic primitives). The passed/failed
 * split is left to the HTTP layer (Collections) so this DTO stays pure.
 */
final readonly class CandidatureSummary
{
    /**
     * @param  list<array{rule: string, passed: bool, reason: string}>  $rules
     */
    public function __construct(
        public string $id,
        public string $fullName,
        public string $email,
        public int $yearsOfExperience,
        public string $cv,
        public string $createdAt,
        public bool $valid,
        public array $rules,
        public ?string $evaluatorName,
        public ?string $assignedAt,
    ) {}
}
