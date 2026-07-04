<?php

declare(strict_types=1);

namespace App\Candidature\Application\Register;

use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\CandidatureRepository;
use App\Candidature\Domain\Exception\CandidatureAlreadyExists;
use App\Candidature\Domain\ValueObject\Cv;
use App\Candidature\Domain\ValueObject\Email;
use App\Candidature\Domain\ValueObject\FullName;
use App\Candidature\Domain\ValueObject\YearsOfExperience;
use DateTimeImmutable;

/**
 * Use case: register a new candidature.
 *
 * Orchestrates the domain — turns primitives into value objects (which enforce their
 * invariants), guards the email-uniqueness business rule, persists via the repository
 * port, and returns a primitive DTO. It depends on the CandidatureRepository interface,
 * never on Eloquent.
 */
final readonly class CandidatureRegistrar
{
    public function __construct(
        private CandidatureRepository $repository,
    ) {}

    public function register(RegisterCandidatureCommand $command): CandidatureResponse
    {
        $email = new Email($command->email);

        // Happy-path uniqueness check; the DB unique index is the race-safe guarantee (see repo).
        if ($this->repository->existsByEmail($email)) {
            throw new CandidatureAlreadyExists($email);
        }

        $candidature = Candidature::register(
            $this->repository->nextIdentity(),
            new FullName($command->fullName),
            $email,
            new YearsOfExperience($command->yearsOfExperience),
            new Cv($command->cv),
            new DateTimeImmutable,
        );

        $this->repository->save($candidature);

        return CandidatureResponse::fromCandidature($candidature);
    }
}
