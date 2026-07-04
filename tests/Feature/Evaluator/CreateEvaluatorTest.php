<?php

declare(strict_types=1);

namespace Tests\Feature\Evaluator;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_evaluator(): void
    {
        $this->postJson('/evaluators', ['name' => 'Grace Hopper'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'name', 'created_at']])
            ->assertJsonPath('data.name', 'Grace Hopper');

        $this->assertDatabaseHas('evaluators', ['name' => 'Grace Hopper']);
    }

    public function test_rejects_an_evaluator_without_a_name(): void
    {
        $this->postJson('/evaluators', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('evaluators', 0);
    }
}
