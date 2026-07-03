<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Http;

use App\Candidature\Application\Register\CandidatureRegistrar;
use App\Candidature\Application\Register\RegisterCandidatureCommand;
use Illuminate\Http\JsonResponse;

/**
 * HTTP entry point for registering a candidature. Thin by design: it builds the command
 * from the validated request, delegates to the use case, and wraps the result. No
 * business logic here.
 */
final readonly class PostCandidatureController
{
    public function __construct(
        private CandidatureRegistrar $registrar,
    ) {}

    public function __invoke(RegisterCandidatureRequest $request): JsonResponse
    {
        $command = new RegisterCandidatureCommand(
            fullName: $request->string('full_name')->toString(),
            email: $request->string('email')->toString(),
            yearsOfExperience: $request->integer('years_of_experience'),
            cv: $request->string('cv')->toString(),
        );

        $candidature = $this->registrar->register($command);

        return CandidatureResource::make($candidature)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
