<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Http;

use App\Candidature\Application\Summary\CandidatureSummaryFinder;
use Illuminate\Http\JsonResponse;

final readonly class GetCandidatureSummaryController
{
    public function __construct(
        private CandidatureSummaryFinder $finder,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        return response()->json(
            CandidatureSummaryResource::toArray($this->finder->summary($id)),
        );
    }
}
