<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Assignment\Infrastructure\Persistence\AssignmentModel;
use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class CandidatureSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_of_a_valid_assigned_candidature(): void
    {
        $candidature = CandidatureModel::factory()->create([
            'email' => 'ada@example.com',
            'years_of_experience' => 9,
        ]);
        $evaluator = EvaluatorModel::factory()->create(['name' => 'Grace Hopper']);
        AssignmentModel::create([
            'candidature_id' => $candidature->id,
            'evaluator_id' => $evaluator->id,
            'assigned_at' => now(),
        ]);

        $response = $this->getJson("/candidatures/{$candidature->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.email', 'ada@example.com')
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.evaluator.name', 'Grace Hopper');

        $this->assertSame([], $response->json('data.validations.failed'));
        $this->assertNotEmpty($response->json('data.validations.passed'));
        $this->assertArrayHasKey('assigned_at', $response->json('data.evaluator'));
    }

    public function test_summary_of_an_ineligible_unassigned_candidature(): void
    {
        $candidature = CandidatureModel::factory()->create(['years_of_experience' => 1]);

        $response = $this->getJson("/candidatures/{$candidature->id}/summary")
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.evaluator', null);

        $failedRules = (new Collection($response->json('data.validations.failed')))->pluck('rule');
        $this->assertTrue($failedRules->contains('minimum_experience'));
    }

    public function test_summary_of_a_missing_candidature_returns_404(): void
    {
        $this->getJson('/candidatures/01ARZ3NDEKTSV4RRFFQ69G5FAV/summary')
            ->assertNotFound();
    }
}
