<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Persistence;

use App\Assignment\Domain\PendingAssignmentReader;
use App\Candidature\Domain\Candidature;
use App\Candidature\Infrastructure\Persistence\CandidatureMapper;
use App\Candidature\Infrastructure\Persistence\CandidatureModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Query Builder read side for bulk auto-assignment (CQRS read).
 */
final readonly class QueryBuilderPendingAssignmentReader implements PendingAssignmentReader
{
    public function __construct(
        private CandidatureMapper $mapper,
    ) {}

    /**
     * @return list<Candidature>
     */
    public function unassignedCandidatures(): array
    {
        $candidatures = CandidatureModel::query()
            ->whereNotExists(function (Builder $query): void {
                $query->select(DB::raw('1'))
                    ->from('assignments')
                    ->whereColumn('assignments.candidature_id', 'candidatures.id');
            })
            ->get()
            ->map(fn (CandidatureModel $model): Candidature => $this->mapper->toDomain($model))
            ->all();

        return array_values($candidatures);
    }

    /**
     * @return array<string, int>
     */
    public function evaluatorLoads(): array
    {
        return DB::table('evaluators')
            ->leftJoin('assignments', 'assignments.evaluator_id', '=', 'evaluators.id')
            ->groupBy('evaluators.id')
            ->selectRaw('evaluators.id as id, COUNT(assignments.id) as evaluator_load')
            ->pluck('evaluator_load', 'id')
            ->map(fn (mixed $load): int => (int) $load)
            ->all();
    }
}
