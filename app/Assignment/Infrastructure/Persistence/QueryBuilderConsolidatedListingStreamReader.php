<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Persistence;

use App\Assignment\Application\Consolidated\ConsolidatedListingQuery;
use App\Assignment\Application\Consolidated\ConsolidatedListingStreamReader;
use App\Assignment\Application\Consolidated\ConsolidatedRow;
use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use Illuminate\Support\Facades\DB;

/**
 * Streaming counterpart of QueryBuilderConsolidatedListingReader: the SAME
 * JOIN + joinSub (COUNT + GROUP_CONCAT) + whitelisted filters + sort, but with NO LIMIT and
 * iterated with a cursor, yielding one ConsolidatedRow at a time. Used by the Excel export
 * to walk every matching row without loading the whole result set into memory.
 *
 * Performance note: `cursor()` streams rows from the driver, but the aggregate derived table
 * is still computed once. Unbuffered PDO cursors for very large exports are deferred to the
 * scalability capability (#7) — see docs/scalability-backlog.md.
 */
final readonly class QueryBuilderConsolidatedListingStreamReader implements ConsolidatedListingStreamReader
{
    /** @var array<string, string> listing column => SQL expression */
    private const SORTABLE = [
        'full_name' => 'c.full_name',
        'email' => 'c.email',
        'years_of_experience' => 'c.years_of_experience',
        'evaluator_name' => 'e.name',
        'assigned_at' => 'a.assigned_at',
        'evaluator_total' => 'stats.total',
    ];

    /** @var array<string, array{column: string, mode: string}> */
    private const FILTERABLE = [
        'full_name' => ['column' => 'c.full_name', 'mode' => 'prefix'],
        'email' => ['column' => 'c.email', 'mode' => 'prefix'],
        'evaluator_name' => ['column' => 'e.name', 'mode' => 'prefix'],
        'years_of_experience' => ['column' => 'c.years_of_experience', 'mode' => 'exact'],
        'assigned_at' => ['column' => 'a.assigned_at', 'mode' => 'exact'],
    ];

    /**
     * @return Generator<ConsolidatedRow>
     */
    public function stream(ConsolidatedListingQuery $query): Generator
    {
        $stats = DB::table('assignments as a2')
            ->join('candidatures as c2', 'c2.id', '=', 'a2.candidature_id')
            ->groupBy('a2.evaluator_id')
            ->select(
                'a2.evaluator_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('GROUP_CONCAT(c2.email) as emails'),
            );

        $builder = DB::table('assignments as a')
            ->join('candidatures as c', 'c.id', '=', 'a.candidature_id')
            ->join('evaluators as e', 'e.id', '=', 'a.evaluator_id')
            ->joinSub($stats, 'stats', 'stats.evaluator_id', '=', 'a.evaluator_id')
            ->select(
                'c.full_name',
                'c.email',
                'c.years_of_experience',
                'e.name as evaluator_name',
                'a.assigned_at',
                'stats.total as evaluator_total',
                'stats.emails as evaluator_candidate_emails',
            );

        foreach ($query->filters as $column => $value) {
            if (! isset(self::FILTERABLE[$column])) {
                continue;
            }

            ['column' => $sqlColumn, 'mode' => $mode] = self::FILTERABLE[$column];

            $mode === 'prefix'
                ? $builder->where($sqlColumn, 'like', $value.'%')
                : $builder->where($sqlColumn, '=', $value);
        }

        $sortColumn = self::SORTABLE[$query->sort] ?? self::SORTABLE['years_of_experience'];
        $direction = strtolower($query->direction) === 'asc' ? 'asc' : 'desc';

        foreach ($builder->orderBy($sortColumn, $direction)->cursor() as $item) {
            /** @var array<string, mixed> $row */
            $row = (array) $item;

            yield new ConsolidatedRow(
                fullName: (string) $row['full_name'],
                email: (string) $row['email'],
                yearsOfExperience: (int) $row['years_of_experience'],
                evaluatorName: (string) $row['evaluator_name'],
                assignedAt: (new DateTimeImmutable((string) $row['assigned_at']))->format(DateTimeInterface::ATOM),
                evaluatorTotal: (int) $row['evaluator_total'],
                evaluatorCandidateEmails: (string) ($row['evaluator_candidate_emails'] ?? ''),
            );
        }
    }
}
