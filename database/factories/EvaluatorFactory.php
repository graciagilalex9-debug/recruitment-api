<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EvaluatorModel>
 */
final class EvaluatorFactory extends Factory
{
    protected $model = EvaluatorModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::ulid(),
            'name' => fake()->name(),
            'created_at' => now(),
        ];
    }
}
