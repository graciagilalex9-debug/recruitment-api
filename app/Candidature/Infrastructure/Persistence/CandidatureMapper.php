<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Persistence;

use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Candidature\Domain\ValueObject\Cv;
use App\Candidature\Domain\ValueObject\Email;
use App\Candidature\Domain\ValueObject\FullName;
use App\Candidature\Domain\ValueObject\YearsOfExperience;
use DateTimeImmutable;

/**
 * Translates between the Candidature aggregate (domain) and its Eloquent model / DB row
 * (infrastructure). This is the ONLY place that knows both worlds, which is what keeps
 * Eloquent out of the domain.
 */
final class CandidatureMapper
{
    public function toDomain(CandidatureModel $model): Candidature
    {
        return Candidature::reconstitute(
            new CandidatureId($model->id),
            new FullName($model->full_name),
            new Email($model->email),
            new YearsOfExperience($model->years_of_experience),
            new Cv($model->cv),
            DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(Candidature $candidature): array
    {
        return [
            'id' => $candidature->id()->value(),
            'full_name' => $candidature->fullName()->value(),
            'email' => $candidature->email()->value(),
            'years_of_experience' => $candidature->yearsOfExperience()->value(),
            'cv' => $candidature->cv()->value(),
            'created_at' => $candidature->createdAt()->format('Y-m-d H:i:s'),
        ];
    }
}
