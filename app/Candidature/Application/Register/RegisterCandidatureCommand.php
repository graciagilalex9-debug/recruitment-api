<?php

declare(strict_types=1);

namespace App\Candidature\Application\Register;

/**
 * Input DTO for the register use case: raw primitives coming from the outside world
 * (the HTTP controller builds it). It carries no behaviour and knows nothing about the
 * domain — that is the point, it decouples the entry point from the aggregate.
 */
final readonly class RegisterCandidatureCommand
{
    public function __construct(
        public string $fullName,
        public string $email,
        public int $yearsOfExperience,
        public string $cv,
    ) {}
}
