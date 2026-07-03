<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the SHAPE of the input at the HTTP boundary (required fields, email format,
 * non-negative experience). Business rules (email uniqueness, eligibility) live in the
 * domain, not here. A failed validation yields a 422 with per-field errors automatically.
 */
final class RegisterCandidatureRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'years_of_experience' => ['required', 'integer', 'min:0'],
            'cv' => ['required', 'string'],
        ];
    }
}
