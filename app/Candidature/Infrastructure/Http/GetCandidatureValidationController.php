<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Http;

use App\Candidature\Application\Validate\CandidatureValidationReader;
use Illuminate\Http\JsonResponse;

/**
 * HTTP entry point for a candidature's eligibility report. Thin: delegates to the read
 * port (a caching decorator over the finder) and wraps the result. The route constrains
 * {id} to a ULID, so a malformed id yields a 404 (route miss) before reaching here.
 */
final readonly class GetCandidatureValidationController
{
    public function __construct(
        private CandidatureValidationReader $reader,
    ) {}

    public function __invoke(string $id): JsonResponse
    {
        $report = $this->reader->validate($id);

        return ValidationReportResource::make($report)->response();
    }
}
