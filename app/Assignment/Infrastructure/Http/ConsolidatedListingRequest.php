<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;

final class ConsolidatedListingRequest extends FormRequest
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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'filter' => ['nullable', 'array'],
        ];
    }
}
