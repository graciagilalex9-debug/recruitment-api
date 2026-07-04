<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Http;

use App\Assignment\Application\Assign\EvaluatorAssigner;
use Illuminate\Http\JsonResponse;

/**
 * Assigns an evaluator to a candidature. The route constrains {id} to a ULID, so a
 * malformed candidature id is a 404 route miss before reaching here.
 */
final readonly class PutCandidatureEvaluatorController
{
    public function __construct(
        private EvaluatorAssigner $assigner,
    ) {}

    public function __invoke(AssignEvaluatorRequest $request, string $id): JsonResponse
    {
        $assignment = $this->assigner->assign($id, $request->string('evaluator_id')->toString());

        return AssignmentResource::make($assignment)->response();
    }
}
