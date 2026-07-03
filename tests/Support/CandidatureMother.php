<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Candidature\Domain\ValueObject\Cv;
use App\Candidature\Domain\ValueObject\Email;
use App\Candidature\Domain\ValueObject\FullName;
use App\Candidature\Domain\ValueObject\YearsOfExperience;
use DateTimeImmutable;

/**
 * Object Mother: builds valid Candidature aggregates for tests, varying only what a
 * given test cares about. Deterministic (fixed id + date) — no framework, no clock.
 */
final class CandidatureMother
{
    public static function withYearsOfExperience(int $years): Candidature
    {
        return Candidature::register(
            new CandidatureId('01BX5ZZKBKACTAV9WEVGEMMVRZ'),
            new FullName('Ada Lovelace'),
            new Email('ada@example.com'),
            new YearsOfExperience($years),
            new Cv('A meaningful CV.'),
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
    }
}
