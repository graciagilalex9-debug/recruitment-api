<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Persistence;

use App\Assignment\Application\Consolidated\ConsolidatedListingQuery;
use App\Assignment\Application\Consolidated\ConsolidatedListingReader;
use App\Assignment\Application\Consolidated\ConsolidatedListingResult;
use App\Assignment\Application\Consolidated\ConsolidatedRow;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Query Builder implementation of the consolidated listing (CQRS read).
 *
 * Performance notes:
 * - Per-evaluator total + concatenated emails come from a single derived table (joinSub),
 *   not a per-row correlated subquery.
 * - Sort/filter columns are whitelisted (security + they map to indexed columns). Text
 *   filters use a prefix LIKE ('value%') so the index is still used; '%contains%' (a full
 *   table scan) is intentionally not offered — see docs/scalability-backlog.md.
 */
final readonly class QueryBuilderConsolidatedListingReader implements ConsolidatedListingReader
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

    public function read(ConsolidatedListingQuery $query): ConsolidatedListingResult
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

        $paginator = $builder
            ->orderBy($sortColumn, $direction)
            ->paginate($query->perPage, ['*'], 'page', $query->page);

        $rows = [];
        foreach ($paginator->items() as $item) {
            /** @var array<string, mixed> $row */
            $row = (array) $item;

            $rows[] = new ConsolidatedRow(
                fullName: (string) $row['full_name'],
                email: (string) $row['email'],
                yearsOfExperience: (int) $row['years_of_experience'],
                evaluatorName: (string) $row['evaluator_name'],
                assignedAt: (new DateTimeImmutable((string) $row['assigned_at']))->format(DateTimeInterface::ATOM),
                evaluatorTotal: (int) $row['evaluator_total'],
                evaluatorCandidateEmails: (string) ($row['evaluator_candidate_emails'] ?? ''),
            );
        }

        return new ConsolidatedListingResult(
            rows: $rows,
            total: $paginator->total(),
            perPage: $paginator->perPage(),
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
        );
    }
}
