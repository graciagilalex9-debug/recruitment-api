<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CandidatureModel>
 */
final class CandidatureFactory extends Factory
{
    protected $model = CandidatureModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'years_of_experience' => fake()->numberBetween(0, 30),
            'cv' => fake()->paragraphs(3, true),
            'created_at' => now(),
        ];
    }
}
