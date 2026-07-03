<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * HTTP integration tests for GET /candidatures/{id}/validation against mysql-test.
 * Each test maps to a scenario in the candidature-validation spec delta.
 */
final class CandidatureValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_an_eligible_candidature_as_valid(): void
    {
        $candidature = CandidatureModel::factory()->create(['years_of_experience' => 9]);

        $response = $this->getJson("/candidatures/{$candidature->id}/validation")
            ->assertOk()
            ->assertJsonPath('data.candidature_id', $candidature->id)
            ->assertJsonPath('data.valid', true);

        $rules = new Collection($response->json('data.rules'));
        $this->assertTrue($rules->every(fn (array $rule): bool => $rule['passed'] === true));
    }

    public function test_reports_an_ineligible_candidature_with_the_failing_rule(): void
    {
        $candidature = CandidatureModel::factory()->create(['years_of_experience' => 1]);

        $response = $this->getJson("/candidatures/{$candidature->id}/validation")
            ->assertOk()
            ->assertJsonPath('data.valid', false);

        $rules = new Collection($response->json('data.rules'));

        $minimumExperience = $rules->firstWhere('rule', 'minimum_experience');
        $this->assertNotNull($minimumExperience);
        $this->assertFalse($minimumExperience['passed']);
        $this->assertNotEmpty($minimumExperience['reason']);

        // The rules it does satisfy are still reported as passed.
        $this->assertTrue($rules->firstWhere('rule', 'has_cv')['passed']);
        $this->assertTrue($rules->firstWhere('rule', 'valid_email')['passed']);
    }

    public function test_returns_404_for_a_missing_candidature(): void
    {
        $this->getJson('/candidatures/01BX5ZZKBKACTAV9WEVGEMMVRZ/validation')
            ->assertNotFound();
    }
}
