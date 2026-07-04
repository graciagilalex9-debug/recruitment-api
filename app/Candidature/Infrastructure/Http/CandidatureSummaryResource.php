<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Http;

use App\Candidature\Application\Summary\CandidatureSummary;
use Illuminate\Support\Collection;

/**
 * Shapes the candidature summary JSON. Uses Collections (a Laravel utility, so it lives in
 * Infrastructure) to split the rule results into passed / failed.
 */
final class CandidatureSummaryResource
{
    /**
     * @return array{data: array<string, mixed>}
     */
    public static function toArray(CandidatureSummary $summary): array
    {
        $rules = new Collection($summary->rules);

        $shape = static fn (array $rule): array => [
            'rule' => $rule['rule'],
            'reason' => $rule['reason'],
        ];

        return [
            'data' => [
                'id' => $summary->id,
                'full_name' => $summary->fullName,
                'email' => $summary->email,
                'years_of_experience' => $summary->yearsOfExperience,
                'cv' => $summary->cv,
                'created_at' => $summary->createdAt,
                'valid' => $summary->valid,
                'validations' => [
                    'passed' => $rules->where('passed', true)->map($shape)->values()->all(),
                    'failed' => $rules->where('passed', false)->map($shape)->values()->all(),
                ],
                'evaluator' => $summary->evaluatorName === null ? null : [
                    'name' => $summary->evaluatorName,
                    'assigned_at' => $summary->assignedAt,
                ],
            ],
        ];
    }
}
