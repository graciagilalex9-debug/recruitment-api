<?php

declare(strict_types=1);

namespace Tests\Unit\Assignment;

use App\Assignment\Application\Assign\EvaluatorAssigner;
use App\Assignment\Domain\Exception\CandidatureNotEligible;
use App\Candidature\Domain\Exception\CandidatureNotFound;
use App\Candidature\Domain\Validation\CandidatureValidator;
use App\Candidature\Domain\Validation\Rule\MustHaveCv;
use App\Candidature\Domain\Validation\Rule\MustHaveMinimumExperience;
use App\Candidature\Domain\Validation\Rule\MustHaveValidEmail;
use App\Evaluator\Domain\Evaluator;
use App\Evaluator\Domain\Exception\EvaluatorNotFound;
use App\Evaluator\Domain\ValueObject\EvaluatorName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\CandidatureMother;
use Tests\Support\InMemoryAssignmentRepository;
use Tests\Support\InMemoryCandidatureRepository;
use Tests\Support\InMemoryEvaluatorRepository;

final class EvaluatorAssignerTest extends TestCase
{
    private const UNKNOWN_ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private InMemoryCandidatureRepository $candidatures;

    private InMemoryEvaluatorRepository $evaluators;

    private InMemoryAssignmentRepository $assignments;

    private EvaluatorAssigner $assigner;

    protected function setUp(): void
    {
        $this->candidatures = new InMemoryCandidatureRepository;
        $this->evaluators = new InMemoryEvaluatorRepository;
        $this->assignments = new InMemoryAssignmentRepository;

        $validator = new CandidatureValidator([
            new MustHaveCv,
            new MustHaveValidEmail,
            new MustHaveMinimumExperience,
        ]);

        $this->assigner = new EvaluatorAssigner(
            $this->candidatures,
            $this->evaluators,
            $this->assignments,
            $validator,
        );
    }

    private function anEvaluator(): Evaluator
    {
        $evaluator = Evaluator::register(
            $this->evaluators->nextIdentity(),
            new EvaluatorName('Grace Hopper'),
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
        $this->evaluators->save($evaluator);

        return $evaluator;
    }

    public function test_assigns_an_evaluator_to_an_eligible_candidature(): void
    {
        $candidature = CandidatureMother::withYearsOfExperience(5);
        $this->candidatures->save($candidature);
        $evaluator = $this->anEvaluator();

        $response = $this->assigner->assign($candidature->id()->value(), $evaluator->id()->value());

        $this->assertSame($candidature->id()->value(), $response->candidatureId);
        $this->assertSame($evaluator->id()->value(), $response->evaluatorId);
        $this->assertSame(1, $this->assignments->count());
    }

    public function test_reassigning_keeps_a_single_assignment(): void
    {
        $candidature = CandidatureMother::withYearsOfExperience(5);
        $this->candidatures->save($candidature);
        $first = $this->anEvaluator();
        $second = $this->anEvaluator();

        $this->assigner->assign($candidature->id()->value(), $first->id()->value());
        $response = $this->assigner->assign($candidature->id()->value(), $second->id()->value());

        $this->assertSame(1, $this->assignments->count());
        $this->assertSame($second->id()->value(), $response->evaluatorId);
    }

    public function test_throws_when_the_candidature_does_not_exist(): void
    {
        $evaluator = $this->anEvaluator();

        $this->expectException(CandidatureNotFound::class);

        $this->assigner->assign(self::UNKNOWN_ULID, $evaluator->id()->value());
    }

    public function test_throws_when_the_evaluator_does_not_exist(): void
    {
        $candidature = CandidatureMother::withYearsOfExperience(5);
        $this->candidatures->save($candidature);

        $this->expectException(EvaluatorNotFound::class);

        $this->assigner->assign($candidature->id()->value(), self::UNKNOWN_ULID);
    }

    public function test_throws_when_the_candidature_is_not_eligible(): void
    {
        $candidature = CandidatureMother::withYearsOfExperience(1); // below the 2-year rule
        $this->candidatures->save($candidature);
        $evaluator = $this->anEvaluator();

        $this->expectException(CandidatureNotEligible::class);

        $this->assigner->assign($candidature->id()->value(), $evaluator->id()->value());
    }
}
