<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Assignment\Infrastructure\Persistence\AssignmentModel;
use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use App\Evaluator\Infrastructure\Persistence\EvaluatorModel;
use Illuminate\Database\Seeder;

/**
 * Assigns a realistic subset of candidatures to evaluators so the consolidated listing and
 * the Excel export always have data out of the box. It mirrors the domain rules: only
 * eligible candidatures (>= 2 years of experience) are assigned, and some are left
 * unassigned so the listing's "excludes unassigned" behaviour is visible too. Candidatures
 * are spread round-robin across evaluators to populate the per-evaluator COUNT/GROUP_CONCAT.
 *
 * Idempotent: it only considers candidatures that are NOT already assigned, so re-running the
 * seeders (`migrate --seed` more than once) never violates the one-assignment-per-candidature
 * unique index — it simply assigns any remaining unassigned eligible ones (or nothing).
 */
final class AssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $evaluators = EvaluatorModel::all();

        if ($evaluators->isEmpty()) {
            return;
        }

        $eligible = CandidatureModel::query()
            ->where('years_of_experience', '>=', 2)
            ->whereNotIn('id', function ($query): void {
                $query->select('candidature_id')->from('assignments');
            })
            ->inRandomOrder()
            ->limit(15)
            ->get();

        $eligible->each(function (CandidatureModel $candidature, int $index) use ($evaluators): void {
            AssignmentModel::create([
                'candidature_id' => $candidature->id,
                'evaluator_id' => $evaluators[$index % $evaluators->count()]->id,
                'assigned_at' => now(),
            ]);
        });
    }
}
