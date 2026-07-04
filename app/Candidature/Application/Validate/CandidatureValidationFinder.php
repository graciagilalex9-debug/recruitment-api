<?php

declare(strict_types=1);

namespace App\Candidature\Application\Validate;

use App\Candidature\Domain\CandidatureRepository;
use App\Candidature\Domain\Exception\CandidatureNotFound;
use App\Candidature\Domain\Validation\CandidatureValidator;
use App\Candidature\Domain\ValueObject\CandidatureId;

/**
 * Use case: report a stored candidature's eligibility.
 *
 * Loads the candidature via the repository port (404 if absent), runs the domain
 * validator, and returns a primitive DTO. Depends on the domain interfaces only.
 */
final readonly class CandidatureValidationFinder implements CandidatureValidationReader
{
    public function __construct(
        private CandidatureRepository $repository,
        private CandidatureValidator $validator,
    ) {}

    public function validate(string $candidatureId): ValidationReportResponse
    {
        $id = new CandidatureId($candidatureId);

        $candidature = $this->repository->findById($id);

        if ($candidature === null) {
            throw new CandidatureNotFound($id);
        }

        $report = $this->validator->validate($candidature);

        return ValidationReportResponse::fromReport($candidature->id()->value(), $report);
    }
}
