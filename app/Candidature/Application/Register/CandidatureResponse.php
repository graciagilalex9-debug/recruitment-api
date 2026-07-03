<?php

declare(strict_types=1);

namespace App\Candidature\Application\Register;

use App\Candidature\Domain\Candidature;
use DateTimeInterface;

/**
 * Output DTO for the register use case: plain primitives the HTTP layer can serialize
 * directly. The domain aggregate never crosses into the controller — this DTO is the
 * boundary. Mapping domain -> primitives happens here, once.
 */
final readonly class CandidatureResponse
{
    public function __construct(
        public string $id,
        public string $fullName,
        public string $email,
        public int $yearsOfExperience,
        public string $cv,
        public string $createdAt,
    ) {}

    public static function fromCandidature(Candidature $candidature): self
    {
        return new self(
            $candidature->id()->value(),
            $candidature->fullName()->value(),
            $candidature->email()->value(),
            $candidature->yearsOfExperience()->value(),
            $candidature->cv()->value(),
            $candidature->createdAt()->format(DateTimeInterface::ATOM),
        );
    }
}
