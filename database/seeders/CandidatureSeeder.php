<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use Illuminate\Database\Seeder;

final class CandidatureSeeder extends Seeder
{
    public function run(): void
    {
        CandidatureModel::factory()->count(25)->create();
    }
}
