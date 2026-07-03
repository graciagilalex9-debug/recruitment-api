<?php

declare(strict_types=1);

namespace App\Candidature\Infrastructure\Persistence;

use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\CandidatureRepository;
use App\Candidature\Domain\Exception\CandidatureAlreadyExists;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Candidature\Domain\ValueObject\Email;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Eloquent implementation of the CandidatureRepository port.
 */
final readonly class EloquentCandidatureRepository implements CandidatureRepository
{
    public function __construct(
        private readonly CandidatureMapper $mapper,
    ) {}

    public function nextIdentity(): CandidatureId
    {
        return new CandidatureId((string) Str::ulid());
    }

    public function findById(CandidatureId $id): ?Candidature
    {
        $model = CandidatureModel::find($id->value());

        return $model === null ? null : $this->mapper->toDomain($model);
    }

    public function existsByEmail(Email $email): bool
    {
        return CandidatureModel::query()
            ->where('email', $email->value())
            ->exists();
    }

    public function save(Candidature $candidature): void
    {
        try {
            CandidatureModel::query()->create($this->mapper->toRow($candidature));
        } catch (QueryException $e) {
            // The unique index on `email` is the race-safe guarantee: two concurrent
            // requests can both pass existsByEmail(), but only one INSERT survives.
            // We translate that integrity violation into the domain exception.
            if ($this->isUniqueViolation($e)) {
                throw new CandidatureAlreadyExists($candidature->email());
            }

            throw $e;
        }
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // SQLSTATE 23000 = integrity constraint violation; the only unique constraint
        // on the candidatures table is `email`.
        return $e->getCode() === '23000';
    }
}
