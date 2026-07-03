<?php

declare(strict_types=1);

namespace Tests\Feature\Candidature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP integration tests for POST /candidatures.
 *
 * These hit the real application against the isolated mysql-test database (RefreshDatabase
 * migrates it and wraps each test in a rolled-back transaction). No internal mocks: the
 * request goes through the real controller → use case → Eloquent repository → MySQL.
 *
 * Each test maps to a scenario in the candidature-registration spec delta.
 */
final class RegisterCandidatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_registers_a_valid_candidature(): void
    {
        $response = $this->postJson('/candidatures', $this->validPayload());

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'full_name', 'email', 'years_of_experience', 'cv', 'created_at'],
            ])
            ->assertJsonPath('data.full_name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada@example.com');

        $this->assertDatabaseHas('candidatures', ['email' => 'ada@example.com']);
    }

    public function test_stores_the_email_normalized_to_lowercase(): void
    {
        $response = $this->postJson('/candidatures', [
            ...$this->validPayload(),
            'email' => 'Ada@Example.COM',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'ada@example.com');
        $this->assertDatabaseHas('candidatures', ['email' => 'ada@example.com']);
    }

    public function test_rejects_a_duplicate_email_with_conflict(): void
    {
        $this->postJson('/candidatures', $this->validPayload())->assertCreated();

        $this->postJson('/candidatures', $this->validPayload())->assertStatus(409);

        $this->assertDatabaseCount('candidatures', 1);
    }

    public function test_rejects_a_duplicate_email_case_insensitively(): void
    {
        $this->postJson('/candidatures', $this->validPayload())->assertCreated();

        $this->postJson('/candidatures', [
            ...$this->validPayload(),
            'email' => 'ADA@EXAMPLE.COM',
        ])->assertStatus(409);

        $this->assertDatabaseCount('candidatures', 1);
    }

    public function test_rejects_a_missing_required_field(): void
    {
        $payload = $this->validPayload();
        unset($payload['email']);

        $this->postJson('/candidatures', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('candidatures', 0);
    }

    public function test_rejects_a_malformed_email(): void
    {
        $this->postJson('/candidatures', [
            ...$this->validPayload(),
            'email' => 'not-an-email',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('candidatures', 0);
    }

    public function test_rejects_negative_years_of_experience(): void
    {
        $this->postJson('/candidatures', [
            ...$this->validPayload(),
            'years_of_experience' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('years_of_experience');

        $this->assertDatabaseCount('candidatures', 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'full_name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'years_of_experience' => 7,
            'cv' => 'Mathematician and first programmer.',
        ];
    }
}
