<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AssignEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private const UNKNOWN_ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private function eligibleCandidature(): CandidatureModel
    {
        return CandidatureModel::factory()->create(['years_of_experience' => 9]);
    }

    public function test_assigns_an_evaluator_to_a_candidature(): void
    {
        $candidature = $this->eligibleCandidature();
        $evaluator = EvaluatorModel::factory()->create();

        $this->putJson("/candidatures/{$candidature->id}/evaluator", ['evaluator_id' => $evaluator->id])
            ->assertOk()
            ->assertJsonPath('data.candidature_id', $candidature->id)
            ->assertJsonPath('data.evaluator_id', $evaluator->id);

        $this->assertDatabaseHas('assignments', [
            'candidature_id' => $candidature->id,
            'evaluator_id' => $evaluator->id,
        ]);
    }

    public function test_one_evaluator_handles_multiple_candidatures(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        $first = $this->eligibleCandidature();
        $second = $this->eligibleCandidature();

        $this->putJson("/candidatures/{$first->id}/evaluator", ['evaluator_id' => $evaluator->id])->assertOk();
        $this->putJson("/candidatures/{$second->id}/evaluator", ['evaluator_id' => $evaluator->id])->assertOk();

        $this->assertDatabaseCount('assignments', 2);
    }

    public function test_reassigning_replaces_the_evaluator(): void
    {
        $candidature = $this->eligibleCandidature();
        $first = EvaluatorModel::factory()->create();
        $second = EvaluatorModel::factory()->create();

        $this->putJson("/candidatures/{$candidature->id}/evaluator", ['evaluator_id' => $first->id])->assertOk();
        $this->putJson("/candidatures/{$candidature->id}/evaluator", ['evaluator_id' => $second->id])->assertOk();

        $this->assertDatabaseCount('assignments', 1);
        $this->assertDatabaseHas('assignments', [
            'candidature_id' => $candidature->id,
            'evaluator_id' => $second->id,
        ]);
    }

    public function test_returns_404_when_the_candidature_is_missing(): void
    {
        $evaluator = EvaluatorModel::factory()->create();

        $this->putJson('/candidatures/'.self::UNKNOWN_ULID.'/evaluator', ['evaluator_id' => $evaluator->id])
            ->assertNotFound();

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_returns_404_when_the_evaluator_is_missing(): void
    {
        $candidature = $this->eligibleCandidature();

        $this->putJson("/candidatures/{$candidature->id}/evaluator", ['evaluator_id' => self::UNKNOWN_ULID])
            ->assertNotFound();

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_returns_422_for_a_malformed_evaluator_id(): void
    {
        $candidature = $this->eligibleCandidature();

        $this->putJson("/candidatures/{$candidature->id}/evaluator", ['evaluator_id' => 'not-a-ulid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('evaluator_id');

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_returns_409_when_the_candidature_is_not_eligible(): void
    {
        $candidature = CandidatureModel::factory()->create(['years_of_experience' => 1]);
        $evaluator = EvaluatorModel::factory()->create();

        $this->putJson("/candidatures/{$candidature->id}/evaluator", ['evaluator_id' => $evaluator->id])
            ->assertStatus(409);

        $this->assertDatabaseCount('assignments', 0);
    }
}
