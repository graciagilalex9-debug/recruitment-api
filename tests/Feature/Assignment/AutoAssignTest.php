<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Assignment\Infrastructure\Persistence\AssignmentModel;
use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AutoAssignTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigns_all_unassigned_eligible_candidatures_balanced(): void
    {
        EvaluatorModel::factory()->count(2)->create();
        CandidatureModel::factory()->count(4)->create(['years_of_experience' => 9]);

        $this->postJson('/candidatures/auto-assign')
            ->assertOk()
            ->assertJsonPath('data.assigned', 4)
            ->assertJsonPath('data.skipped_ineligible', 0);

        $this->assertDatabaseCount('assignments', 4);

        // 2 evaluators, 4 candidatures → 2 each.
        $perEvaluator = AssignmentModel::query()
            ->selectRaw('evaluator_id, COUNT(*) as total')
            ->groupBy('evaluator_id')
            ->pluck('total')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $this->assertEqualsCanonicalizing([2, 2], $perEvaluator);
    }

    public function test_skips_ineligible_candidatures(): void
    {
        EvaluatorModel::factory()->create();
        CandidatureModel::factory()->create(['years_of_experience' => 9]); // eligible
        CandidatureModel::factory()->create(['years_of_experience' => 1]); // ineligible

        $this->postJson('/candidatures/auto-assign')
            ->assertOk()
            ->assertJsonPath('data.assigned', 1)
            ->assertJsonPath('data.skipped_ineligible', 1);

        $this->assertDatabaseCount('assignments', 1);
    }

    public function test_leaves_already_assigned_candidatures_untouched(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        EvaluatorModel::factory()->create(); // another, less loaded
        $candidature = CandidatureModel::factory()->create(['years_of_experience' => 9]);

        AssignmentModel::create([
            'candidature_id' => $candidature->id,
            'evaluator_id' => $evaluator->id,
            'assigned_at' => now(),
        ]);

        $this->postJson('/candidatures/auto-assign')
            ->assertOk()
            ->assertJsonPath('data.assigned', 0);

        $this->assertDatabaseCount('assignments', 1);
        $this->assertDatabaseHas('assignments', [
            'candidature_id' => $candidature->id,
            'evaluator_id' => $evaluator->id,
        ]);
    }

    public function test_nothing_to_assign_returns_zero(): void
    {
        EvaluatorModel::factory()->create();

        $this->postJson('/candidatures/auto-assign')
            ->assertOk()
            ->assertJsonPath('data.assigned', 0);
    }

    public function test_returns_409_when_eligible_candidatures_but_no_evaluators(): void
    {
        CandidatureModel::factory()->create(['years_of_experience' => 9]);

        $this->postJson('/candidatures/auto-assign')
            ->assertStatus(409);

        $this->assertDatabaseCount('assignments', 0);
    }
}
