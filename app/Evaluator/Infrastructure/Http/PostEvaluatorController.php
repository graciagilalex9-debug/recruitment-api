<?php

declare(strict_types=1);

namespace App\Evaluator\Infrastructure\Http;

use App\Evaluator\Application\Register\EvaluatorCreator;
use App\Evaluator\Application\Register\RegisterEvaluatorCommand;
use Illuminate\Http\JsonResponse;

final readonly class PostEvaluatorController
{
    public function __construct(
        private EvaluatorCreator $creator,
    ) {}

    public function __invoke(RegisterEvaluatorRequest $request): JsonResponse
    {
        $command = new RegisterEvaluatorCommand($request->string('name')->toString());

        $evaluator = $this->creator->create($command);

        return EvaluatorResource::make($evaluator)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
