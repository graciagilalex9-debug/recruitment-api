<?php

declare(strict_types=1);

namespace App\Candidature\Application\Validate;

/**
 * Port for reading a candidature's eligibility report. Extracted so the read can be decorated
 * (e.g. with caching) without the HTTP layer depending on a concrete implementation.
 * `CandidatureValidationFinder` is the real implementation.
 */
interface CandidatureValidationReader
{
    public function validate(string $candidatureId): ValidationReportResponse;
}
