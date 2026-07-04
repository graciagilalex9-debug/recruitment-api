<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Assignment\Domain\Assignment;
use App\Assignment\Domain\AssignmentRepository;
use App\Assignment\Infrastructure\Persistence\AssignmentModel;
use App\Assignment\Infrastructure\Persistence\EloquentAssignmentRepository;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class AutoAssignTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failure_midway_rolls_back_the_whole_batch(): void
    {
        EvaluatorModel::factory()->create();
        CandidatureModel::factory()->count(3)->create(['years_of_experience' => 5]);

        // A repository that persists for real but throws on the 2nd save, simulating a
        // failure part-way through the batch.
        $this->app->bind(AssignmentRepository::class, fn ($app): AssignmentRepository => new class($app->make(EloquentAssignmentRepository::class)) implements AssignmentRepository
        {
            private int $saves = 0;

            public function __construct(private readonly AssignmentRepository $inner) {}

            public function save(Assignment $assignment): void
            {
                if (++$this->saves === 2) {
                    throw new RuntimeException('simulated failure mid-batch');
                }

                $this->inner->save($assignment);
            }

            public function findByCandidature(CandidatureId $candidatureId): ?Assignment
            {
                return $this->inner->findByCandidature($candidatureId);
            }
        });

        $this->postJson('/candidatures/auto-assign')->assertStatus(500);

        // Atomic: the first (successful) insert must have been rolled back with the batch.
        $this->assertSame(0, AssignmentModel::query()->count());
    }
}
