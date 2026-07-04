<?php

declare(strict_types=1);

namespace App\Evaluator\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;

final class RegisterEvaluatorRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
