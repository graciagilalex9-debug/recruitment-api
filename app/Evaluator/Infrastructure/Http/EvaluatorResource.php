<?php

declare(strict_types=1);

namespace App\Evaluator\Infrastructure\Http;

use App\Evaluator\Application\Register\EvaluatorResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EvaluatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EvaluatorResponse $evaluator */
        $evaluator = $this->resource;

        return [
            'id' => $evaluator->id,
            'name' => $evaluator->name,
            'created_at' => $evaluator->createdAt,
        ];
    }
}
