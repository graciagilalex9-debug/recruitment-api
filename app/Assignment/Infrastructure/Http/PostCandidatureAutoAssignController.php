<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Http;

use App\Assignment\Application\AutoAssign\CandidatureAutoAssigner;
use Illuminate\Http\JsonResponse;

/**
 * Bulk auto-assignment entry point. No request body: it processes the whole backlog of
 * unassigned, eligible candidatures.
 */
final readonly class PostCandidatureAutoAssignController
{
    public function __construct(
        private CandidatureAutoAssigner $assigner,
    ) {}

    public function __invoke(): JsonResponse
    {
        $summary = $this->assigner->assignAll();

        return AutoAssignmentResource::make($summary)->response();
    }
}
