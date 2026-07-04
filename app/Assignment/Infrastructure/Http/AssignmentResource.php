<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Http;

use App\Assignment\Application\Assign\AssignmentResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AssignmentResponse $assignment */
        $assignment = $this->resource;

        return [
            'candidature_id' => $assignment->candidatureId,
            'evaluator_id' => $assignment->evaluatorId,
            'assigned_at' => $assignment->assignedAt,
        ];
    }
}
