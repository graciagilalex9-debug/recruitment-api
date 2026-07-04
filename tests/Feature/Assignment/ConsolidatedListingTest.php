<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Assignment\Infrastructure\Persistence\AssignmentModel;
use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

final class ConsolidatedListingTest extends TestCase
{
    use RefreshDatabase;

    private function assign(CandidatureModel $candidature, EvaluatorModel $evaluator): void
    {
        AssignmentModel::create([
            'candidature_id' => $candidature->id,
            'evaluator_id' => $evaluator->id,
            'assigned_at' => now(),
        ]);
    }

    public function test_returns_assigned_candidatures_with_evaluator_context(): void
    {
        $evaluator = EvaluatorModel::factory()->create(['name' => 'Grace Hopper']);
        $ada = CandidatureModel::factory()->create(['email' => 'ada@example.com', 'years_of_experience' => 9]);
        $alan = CandidatureModel::factory()->create(['email' => 'alan@example.com', 'years_of_experience' => 7]);
        $this->assign($ada, $evaluator);
        $this->assign($alan, $evaluator);

        $rows = new Collection($this->getJson('/candidatures/consolidated')->assertOk()->json('data'));
        $row = $rows->firstWhere('email', 'ada@example.com');

        $this->assertNotNull($row);
        $this->assertSame('Grace Hopper', $row['evaluator_name']);
        $this->assertArrayHasKey('assigned_at', $row);
        $this->assertSame(2, $row['evaluator_total']);
        $this->assertStringContainsString('ada@example.com', $row['evaluator_candidate_emails']);
        $this->assertStringContainsString('alan@example.com', $row['evaluator_candidate_emails']);
    }

    public function test_excludes_candidatures_without_an_evaluator(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        $assigned = CandidatureModel::factory()->create(['email' => 'assigned@example.com']);
        CandidatureModel::factory()->create(['email' => 'lonely@example.com']); // unassigned
        $this->assign($assigned, $evaluator);

        $emails = (new Collection($this->getJson('/candidatures/consolidated')->json('data')))->pluck('email');

        $this->assertTrue($emails->contains('assigned@example.com'));
        $this->assertFalse($emails->contains('lonely@example.com'));
        $this->assertSame(1, $this->getJson('/candidatures/consolidated')->json('meta.total'));
    }

    public function test_default_order_is_years_of_experience_descending(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        $this->assign(CandidatureModel::factory()->create(['years_of_experience' => 3]), $evaluator);
        $this->assign(CandidatureModel::factory()->create(['years_of_experience' => 9]), $evaluator);

        $years = (new Collection($this->getJson('/candidatures/consolidated')->json('data')))
            ->pluck('years_of_experience')
            ->all();

        $this->assertSame([9, 3], $years);
    }

    public function test_orders_by_a_chosen_column_and_direction(): void
    {
        $bob = EvaluatorModel::factory()->create(['name' => 'Bob']);
        $alice = EvaluatorModel::factory()->create(['name' => 'Alice']);
        $this->assign(CandidatureModel::factory()->create(), $bob);
        $this->assign(CandidatureModel::factory()->create(), $alice);

        $names = (new Collection($this->getJson('/candidatures/consolidated?sort=evaluator_name&direction=asc')->json('data')))
            ->pluck('evaluator_name')
            ->all();

        $this->assertSame(['Alice', 'Bob'], $names);
    }

    public function test_filters_by_a_listed_column(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        $this->assign(CandidatureModel::factory()->create(['full_name' => 'Ada Lovelace']), $evaluator);
        $this->assign(CandidatureModel::factory()->create(['full_name' => 'Alan Turing']), $evaluator);

        $names = (new Collection($this->getJson('/candidatures/consolidated?filter[full_name]=Ada')->json('data')))
            ->pluck('full_name')
            ->all();

        $this->assertSame(['Ada Lovelace'], $names);
    }

    public function test_paginates_the_listing(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        foreach (CandidatureModel::factory()->count(3)->create() as $candidature) {
            $this->assign($candidature, $evaluator);
        }

        $response = $this->getJson('/candidatures/consolidated?per_page=2')->assertOk();

        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.per_page'));
        $this->assertSame(1, $response->json('meta.current_page'));
        $this->assertSame(2, $response->json('meta.last_page'));
    }

    public function test_rejects_an_unknown_sort_column(): void
    {
        $this->getJson('/candidatures/consolidated?sort=drop_table')->assertStatus(422);
    }
}
