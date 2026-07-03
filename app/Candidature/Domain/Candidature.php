<?php

declare(strict_types=1);

namespace App\Candidature\Domain;

use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Candidature\Domain\ValueObject\Cv;
use App\Candidature\Domain\ValueObject\Email;
use App\Candidature\Domain\ValueObject\FullName;
use App\Candidature\Domain\ValueObject\YearsOfExperience;
use DateTimeImmutable;

/**
 * Candidature aggregate root.
 *
 * Composed entirely of value objects, so an instance is always valid by construction.
 * It is immutable and has no setters — state is only established through the named
 * constructors. Pure domain: it depends on nothing from Laravel (DateTimeImmutable is
 * native PHP, not Carbon).
 */
final readonly class Candidature
{
    private function __construct(
        private CandidatureId $id,
        private FullName $fullName,
        private Email $email,
        private YearsOfExperience $yearsOfExperience,
        private Cv $cv,
        private DateTimeImmutable $createdAt,
    ) {}

    /**
     * Create a brand-new candidature. Expresses the business intent of registering;
     * this is where a CandidatureRegistered domain event would be raised in the future.
     * `createdAt` is supplied by the caller (application layer) so the domain stays
     * deterministic and clock-free.
     */
    public static function register(
        CandidatureId $id,
        FullName $fullName,
        Email $email,
        YearsOfExperience $yearsOfExperience,
        Cv $cv,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $fullName, $email, $yearsOfExperience, $cv, $createdAt);
    }

    /**
     * Rebuild an existing candidature from persisted data (used by the mapper).
     * Never raises domain events — it only reconstitutes known state.
     */
    public static function reconstitute(
        CandidatureId $id,
        FullName $fullName,
        Email $email,
        YearsOfExperience $yearsOfExperience,
        Cv $cv,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $fullName, $email, $yearsOfExperience, $cv, $createdAt);
    }

    public function id(): CandidatureId
    {
        return $this->id;
    }

    public function fullName(): FullName
    {
        return $this->fullName;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function yearsOfExperience(): YearsOfExperience
    {
        return $this->yearsOfExperience;
    }

    public function cv(): Cv
    {
        return $this->cv;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
