<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Http;

use App\Candidature\Application\Validate\CandidatureValidationFinder;
use Illuminate\Http\JsonResponse;

/**
 * HTTP entry point for a candidature's eligibility report. Thin: delegates to the use
 * case and wraps the result. The route constrains {id} to a ULID, so a malformed id
 * yields a 404 (route miss) before reaching here.
 */
final readonly class GetCandidatureValidationController
{
    public function __construct(
        private CandidatureValidationFinder $finder,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $report = $this->finder->validate($id);

        return ValidationReportResource::make($report)->response();
    }
}
