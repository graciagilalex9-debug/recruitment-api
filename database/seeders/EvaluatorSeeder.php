<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Database\Seeder;

final class EvaluatorSeeder extends Seeder
{
    public function run(): void
    {
        EvaluatorModel::factory()->count(5)->create();
    }
}
