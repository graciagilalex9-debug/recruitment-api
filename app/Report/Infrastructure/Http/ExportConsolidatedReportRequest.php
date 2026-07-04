<?php

declare(strict_types=1);

namespace App\Report\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an export request. It accepts the same sort/direction/filter parameters as the
 * consolidated listing (same whitelist), minus pagination — an export always covers the full
 * matching set. An unknown sort column or bad direction is rejected with 422.
 */
final class ExportConsolidatedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sort' => ['nullable', 'string', 'in:full_name,email,years_of_experience,evaluator_name,assigned_at,evaluator_total'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'filter' => ['nullable', 'array'],
        ];
    }
}
