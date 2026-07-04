<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Http;

use App\Assignment\Application\AutoAssign\AutoAssignmentResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AutoAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AutoAssignmentResponse $summary */
        $summary = $this->resource;

        return [
            'assigned' => $summary->assigned,
            'skipped_ineligible' => $summary->skippedIneligible,
        ];
    }
}
