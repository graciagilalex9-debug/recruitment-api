<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Http;

use App\Candidature\Application\Register\CandidatureResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes the JSON representation of a candidature (snake_case public contract) from the
 * application DTO. Keeping the wire format here means the DTO stays a neutral primitive
 * carrier and the JSON contract lives in the HTTP layer. JsonResource wraps it in "data".
 */
final class CandidatureResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CandidatureResponse $candidature */
        $candidature = $this->resource;

        return [
            'id' => $candidature->id,
            'full_name' => $candidature->fullName,
            'email' => $candidature->email,
            'years_of_experience' => $candidature->yearsOfExperience,
            'cv' => $candidature->cv,
            'created_at' => $candidature->createdAt,
        ];
    }
}
