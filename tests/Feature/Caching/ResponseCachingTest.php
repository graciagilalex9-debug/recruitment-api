<?php

declare(strict_types=1);

namespace Tests\Feature\Caching;

use App\Assignment\Domain\Assignment;
use App\Assignment\Domain\AssignmentRepository;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use App\Evaluator\Domain\ValueObject\EvaluatorId;
use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ResponseCachingTest extends TestCase
{
    use RefreshDatabase;

    /** Assign through the real repository so the caching decorator bumps the listing version. */
    private function assign(string $candidatureId, string $evaluatorId): void
    {
        app(AssignmentRepository::class)->save(
            Assignment::assign(
                new CandidatureId($candidatureId),
                new EvaluatorId($evaluatorId),
                new DateTimeImmutable,
            ),
        );
    }

    public function test_the_listing_reflects_a_newly_created_assignment(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        $this->assign(CandidatureModel::factory()->create()->id, $evaluator->id);

        // Warm the cache: one assigned candidature.
        $this->getJson('/candidatures/consolidated')->assertOk()->assertJsonPath('meta.total', 1);

        // A new assignment must invalidate the cache (version bump on save).
        $this->assign(CandidatureModel::factory()->create()->id, $evaluator->id);

        $this->getJson('/candidatures/consolidated')->assertOk()->assertJsonPath('meta.total', 2);
    }

    public function test_repeated_listing_requests_are_served_from_cache(): void
    {
        $evaluator = EvaluatorModel::factory()->create();
        $this->assign(CandidatureModel::factory()->create()->id, $evaluator->id);

        $first = $this->getJson('/candidatures/consolidated')->assertOk()->json();

        DB::connection()->enableQueryLog();
        $second = $this->getJson('/candidatures/consolidated')->assertOk()->json();

        $this->assertEmpty(DB::connection()->getQueryLog(), 'A cached listing must not hit the database.');
        $this->assertSame($first, $second);
    }

    public function test_repeated_validation_requests_are_served_from_cache(): void
    {
        $candidature = CandidatureModel::factory()->create(['years_of_experience' => 5]);

        $first = $this->getJson("/candidatures/{$candidature->id}/validation")->assertOk()->json();

        DB::connection()->enableQueryLog();
        $second = $this->getJson("/candidatures/{$candidature->id}/validation")->assertOk()->json();

        $this->assertEmpty(DB::connection()->getQueryLog(), 'A cached validation report must not hit the database.');
        $this->assertSame($first, $second);
    }
}
