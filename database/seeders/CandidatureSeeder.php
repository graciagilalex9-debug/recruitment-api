<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use Illuminate\Database\Seeder;

final class CandidatureSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: don't pile up more sample data if it's already seeded.
        if (CandidatureModel::query()->exists()) {
            return;
        }

        CandidatureModel::factory()->count(25)->create();
    }
}
