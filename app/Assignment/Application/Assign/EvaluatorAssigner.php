<?php

declare(strict_types=1);

namespace App\Assignment\Application\Assign;

use App\Assignment\Domain\Assignment;
use App\Assignment\Domain\AssignmentRepository;
use App\Assignment\Domain\Exception\CandidatureNotEligible;
use App\Candidature\Domain\CandidatureRepository;
use App\Candidature\Domain\Exception\CandidatureNotFound;
use App\Candidature\Domain\Validation\CandidatureValidator;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Evaluator\Domain\EvaluatorRepository;
use App\Evaluator\Domain\Exception\EvaluatorNotFound;
use App\Evaluator\Domain\ValueObject\EvaluatorId;
use DateTimeImmutable;

/**
 * Use case: assign an evaluator to a candidature.
 *
 * Orchestrates three contexts' ports. Guards: the candidature and the evaluator must
 * exist (404), and the candidature must be eligible — reusing the candidature-validation
 * rules (our own gate; not in the brief) → 409 if not. The assignment upserts (one current
 * evaluator per candidature).
 */
final readonly class EvaluatorAssigner
{
    public function __construct(
        private CandidatureRepository $candidatures,
        private EvaluatorRepository $evaluators,
        private AssignmentRepository $assignments,
        private CandidatureValidator $validator,
    ) {}

    public function assign(string $candidatureId, string $evaluatorId): AssignmentResponse
    {
        $candidatureIdVo = new CandidatureId($candidatureId);
        $candidature = $this->candidatures->findById($candidatureIdVo);

        if ($candidature === null) {
            throw new CandidatureNotFound($candidatureIdVo);
        }

        $evaluatorIdVo = new EvaluatorId($evaluatorId);

        if ($this->evaluators->findById($evaluatorIdVo) === null) {
            throw new EvaluatorNotFound($evaluatorIdVo);
        }

        if (! $this->validator->validate($candidature)->isValid()) {
            throw new CandidatureNotEligible($candidatureIdVo);
        }

        $assignment = Assignment::assign($candidatureIdVo, $evaluatorIdVo, new DateTimeImmutable);
        $this->assignments->save($assignment);

        return AssignmentResponse::fromAssignment($assignment);
    }
}
