<?php

declare(strict_types=1);

namespace Tests\Unit\Assignment;

use App\Assignment\Application\AutoAssign\CandidatureAutoAssigner;
use App\Assignment\Domain\Exception\NoEvaluatorsAvailable;
use App\Candidature\Domain\Validation\CandidatureValidator;
use App\Candidature\Domain\Validation\Rule\MustHaveCv;
use App\Candidature\Domain\Validation\Rule\MustHaveMinimumExperience;
use App\Candidature\Domain\Validation\Rule\MustHaveValidEmail;
use App\Candidature\Domain\ValueObject\CandidatureId;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use Tests\Support\CandidatureMother;
use Tests\Support\FakePendingAssignmentReader;
use Tests\Support\ImmediateMutex;
use Tests\Support\ImmediateTransactionManager;
use Tests\Support\InMemoryAssignmentRepository;

final class CandidatureAutoAssignerTest extends TestCase
{
    private InMemoryAssignmentRepository $assignments;

    protected function setUp(): void
    {
        $this->assignments = new InMemoryAssignmentRepository;
    }

    private function assigner(FakePendingAssignmentReader $reader): CandidatureAutoAssigner
    {
        return new CandidatureAutoAssigner(
            $reader,
            $this->assignments,
            new CandidatureValidator([new MustHaveCv, new MustHaveValidEmail, new MustHaveMinimumExperience]),
            new ImmediateMutex,
            new ImmediateTransactionManager,
        );
    }

    public function test_assigns_to_the_least_loaded_evaluator(): void
    {
        $busy = (string) Str::ulid();
        $idle = (string) Str::ulid();
        $candidateId = (string) Str::ulid();

        $reader = new FakePendingAssignmentReader(
            [CandidatureMother::create($candidateId, 5)],
            [$busy => 5, $idle => 0],
        );

        $response = $this->assigner($reader)->assignAll();

        $this->assertSame(1, $response->assigned);
        $assignment = $this->assignments->findByCandidature(new CandidatureId($candidateId));
        $this->assertNotNull($assignment);
        $this->assertSame($idle, $assignment->evaluatorId()->value());
    }

    public function test_distributes_evenly_across_evaluators(): void
    {
        $first = (string) Str::ulid();
        $second = (string) Str::ulid();
        $a = (string) Str::ulid();
        $b = (string) Str::ulid();

        $reader = new FakePendingAssignmentReader(
            [CandidatureMother::create($a, 5), CandidatureMother::create($b, 5)],
            [$first => 0, $second => 0],
        );

        $response = $this->assigner($reader)->assignAll();

        $this->assertSame(2, $response->assigned);
        $evaluators = [
            $this->assignments->findByCandidature(new CandidatureId($a))?->evaluatorId()->value(),
            $this->assignments->findByCandidature(new CandidatureId($b))?->evaluatorId()->value(),
        ];
        $this->assertEqualsCanonicalizing([$first, $second], $evaluators);
    }

    public function test_skips_ineligible_candidatures(): void
    {
        $reader = new FakePendingAssignmentReader(
            [
                CandidatureMother::create((string) Str::ulid(), 5),  // eligible
                CandidatureMother::create((string) Str::ulid(), 1),  // ineligible (< 2 years)
            ],
            [(string) Str::ulid() => 0],
        );

        $response = $this->assigner($reader)->assignAll();

        $this->assertSame(1, $response->assigned);
        $this->assertSame(1, $response->skippedIneligible);
        $this->assertSame(1, $this->assignments->count());
    }

    public function test_throws_when_there_are_eligible_candidatures_but_no_evaluators(): void
    {
        $reader = new FakePendingAssignmentReader(
            [CandidatureMother::create((string) Str::ulid(), 5)],
            [],
        );

        $this->expectException(NoEvaluatorsAvailable::class);

        $this->assigner($reader)->assignAll();
    }
}
