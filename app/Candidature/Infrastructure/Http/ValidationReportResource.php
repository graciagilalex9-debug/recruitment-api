<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Http;

use App\Candidature\Application\Validate\RuleResultResponse;
use App\Candidature\Application\Validate\ValidationReportResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the validation report JSON (snake_case, wrapped in "data") from the application
 * DTO. Keeps the wire format in the HTTP layer.
 */
final class ValidationReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ValidationReportResponse $report */
        $report = $this->resource;

        return [
            'candidature_id' => $report->candidatureId,
            'valid' => $report->valid,
            'rules' => collect($report->rules)
                ->map(static fn (RuleResultResponse $rule): array => [
                    'rule' => $rule->key,
                    'passed' => $rule->passed,
                    'reason' => $rule->reason,
                ])
                ->all(),
        ];
    }
}
