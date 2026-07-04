<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;

final class AssignEvaluatorRequest extends FormRequest
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
            'evaluator_id' => ['required', 'ulid'],
        ];
    }
}
