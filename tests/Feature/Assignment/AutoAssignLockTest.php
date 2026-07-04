<?php

declare(strict_types=1);

namespace Tests\Feature\Assignment;

use App\Assignment\Infrastructure\Persistence\AssignmentModel;
use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class AutoAssignLockTest extends TestCase
{
    use RefreshDatabase;

    private function seedBacklog(): void
    {
        EvaluatorModel::factory()->create();
        CandidatureModel::factory()->count(3)->create(['years_of_experience' => 5]); // eligible, unassigned
    }

    public function test_a_concurrent_auto_assign_is_rejected(): void
    {
        $this->seedBacklog();

        // Simulate another run already holding the lock.
        $lock = Cache::lock('auto-assign', 30);
        $this->assertTrue($lock->get());

        try {
            $this->postJson('/candidatures/auto-assign')->assertStatus(409);
            $this->assertSame(0, AssignmentModel::query()->count(), 'The rejected run must not assign anything.');
        } finally {
            $lock->release();
        }
    }

    public function test_auto_assign_works_again_once_the_previous_run_finished(): void
    {
        $this->seedBacklog();

        // First run assigns the backlog; it must release the lock on completion.
        $this->postJson('/candidatures/auto-assign')->assertOk();

        // Second run acquires the lock fine (it just finds no backlog left).
        $this->postJson('/candidatures/auto-assign')->assertOk();

        $this->assertSame(3, AssignmentModel::query()->count());
    }
}
