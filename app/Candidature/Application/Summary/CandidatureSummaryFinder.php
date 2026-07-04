<?php

declare(strict_types=1);

namespace App\Candidature\Application\Summary;

use App\Assignment\Domain\AssignmentRepository;
use App\Candidature\Domain\CandidatureRepository;
use App\Candidature\Domain\Exception\CandidatureNotFound;
use App\Candidature\Domain\Validation\CandidatureValidator;
use App\Candidature\Domain\Validation\RuleResult;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Evaluator\Domain\EvaluatorRepository;
use DateTimeInterface;

/**
 * Use case: build a candidature's consolidated summary. Composes existing behaviour —
 * the validator (#2) and the assignment/evaluator lookups (#3) — into one DTO.
 */
final readonly class CandidatureSummaryFinder
{
    public function __construct(
        private CandidatureRepository $candidatures,
        private CandidatureValidator $validator,
        private AssignmentRepository $assignments,
        private EvaluatorRepository $evaluators,
    ) {}

    public function summary(string $candidatureId): CandidatureSummary
    {
        $id = new CandidatureId($candidatureId);

        $candidature = $this->candidatures->findById($id);

        if ($candidature === null) {
            throw new CandidatureNotFound($id);
        }

        $report = $this->validator->validate($candidature);

        $rules = array_map(
            static fn (RuleResult $result): array => [
                'rule' => $result->key(),
                'passed' => $result->hasPassed(),
                'reason' => $result->reason(),
            ],
            $report->results(),
        );

        $assignment = $this->assignments->findByCandidature($id);
        $evaluator = $assignment !== null
            ? $this->evaluators->findById($assignment->evaluatorId())
            : null;

        return new CandidatureSummary(
            id: $candidature->id()->value(),
            fullName: $candidature->fullName()->value(),
            email: $candidature->email()->value(),
            yearsOfExperience: $candidature->yearsOfExperience()->value(),
            cv: $candidature->cv()->value(),
            createdAt: $candidature->createdAt()->format(DateTimeInterface::ATOM),
            valid: $report->isValid(),
            rules: $rules,
            evaluatorName: $evaluator?->name()->value(),
            assignedAt: $assignment?->assignedAt()->format(DateTimeInterface::ATOM),
        );
    }
}
