<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Http;

use App\Assignment\Application\Consolidated\ConsolidatedListingResult;
use App\Assignment\Application\Consolidated\ConsolidatedRow;

/**
 * Shapes the paginated JSON ({ data[], meta{} }) from the application result. A plain
 * presenter (not a JsonResource) to keep the paginated envelope explicit and avoid
 * double-wrapping.
 */
final class ConsolidatedListingResource
{
    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public static function toArray(ConsolidatedListingResult $result): array
    {
        return [
            'data' => array_map(
                static fn (ConsolidatedRow $row): array => [
                    'full_name' => $row->fullName,
                    'email' => $row->email,
                    'years_of_experience' => $row->yearsOfExperience,
                    'evaluator_name' => $row->evaluatorName,
                    'assigned_at' => $row->assignedAt,
                    'evaluator_total' => $row->evaluatorTotal,
                    'evaluator_candidate_emails' => $row->evaluatorCandidateEmails,
                ],
                $result->rows,
            ),
            'meta' => [
                'current_page' => $result->currentPage,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'last_page' => $result->lastPage,
            ],
        ];
    }
}
