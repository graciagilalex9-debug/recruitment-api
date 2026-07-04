<?php

declare(strict_types=1);

namespace App\Assignment\Application\AutoAssign;

use App\Assignment\Application\Lock\LockNotAcquired;
use App\Assignment\Application\Lock\Mutex;
use App\Assignment\Domain\Assignment;
use App\Assignment\Domain\AssignmentRepository;
use App\Assignment\Domain\Exception\AutoAssignInProgress;
use App\Assignment\Domain\Exception\NoEvaluatorsAvailable;
use App\Assignment\Domain\PendingAssignmentReader;
use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\Validation\CandidatureValidator;
use App\Evaluator\Domain\ValueObject\EvaluatorId;
use DateTimeImmutable;

/**
 * Use case: assign every unassigned, eligible candidature to the least-loaded evaluator.
 *
 * Eligibility is the domain rules (reused validator), not a SQL filter. Balancing keeps a
 * live tally of per-evaluator load, picking the minimum each time so the distribution stays
 * even in a single pass.
 */
final readonly class CandidatureAutoAssigner
{
    private const LOCK = 'auto-assign';

    public function __construct(
        private PendingAssignmentReader $reader,
        private AssignmentRepository $assignments,
        private CandidatureValidator $validator,
        private Mutex $mutex,
    ) {}

    /**
     * Serialize the whole bulk operation: reading the backlog and distributing it is not
     * atomic, so two concurrent runs would work from the same snapshot and collide. Only one
     * runs at a time; a concurrent request fails fast with AutoAssignInProgress (409).
     */
    public function assignAll(): AutoAssignmentResponse
    {
        try {
            return $this->mutex->withLock(self::LOCK, fn (): AutoAssignmentResponse => $this->run());
        } catch (LockNotAcquired) {
            throw new AutoAssignInProgress;
        }
    }

    private function run(): AutoAssignmentResponse
    {
        $loads = $this->reader->evaluatorLoads();
        $candidatures = $this->reader->unassignedCandidatures();

        $eligible = array_values(array_filter(
            $candidatures,
            fn (Candidature $candidature): bool => $this->validator->validate($candidature)->isValid(),
        ));

        $skippedIneligible = count($candidatures) - count($eligible);

        if ($eligible !== [] && $loads === []) {
            throw new NoEvaluatorsAvailable;
        }

        $assigned = 0;
        foreach ($eligible as $candidature) {
            $evaluatorId = $this->leastLoaded($loads);

            $this->assignments->save(Assignment::assign(
                $candidature->id(),
                new EvaluatorId($evaluatorId),
                new DateTimeImmutable,
            ));

            $loads[$evaluatorId]++;
            $assigned++;
        }

        return new AutoAssignmentResponse($assigned, $skippedIneligible);
    }

    /**
     * @param  array<string, int>  $loads  a non-empty map of evaluatorId => current load
     */
    private function leastLoaded(array $loads): string
    {
        $chosen = '';
        $min = null;

        foreach ($loads as $evaluatorId => $load) {
            if ($min === null || $load < $min) {
                $min = $load;
                $chosen = $evaluatorId;
            }
        }

        return $chosen;
    }
}
