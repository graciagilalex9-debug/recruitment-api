<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Database\Seeder;

final class EvaluatorSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: don't pile up more sample data if it's already seeded.
        if (EvaluatorModel::query()->exists()) {
            return;
        }

        EvaluatorModel::factory()->count(5)->create();
    }
}
