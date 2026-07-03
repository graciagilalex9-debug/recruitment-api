<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\CandidatureRepository;
use App\Candidature\Domain\Exception\CandidatureAlreadyExists;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Candidature\Domain\ValueObject\Email;
use Illuminate\Support\Str;

/**
 * In-memory implementation of the CandidatureRepository port for unit tests.
 *
 * This is a FAKE, not a mock: it is a real (if simple) implementation that actually
 * stores candidatures and enforces email uniqueness, exactly like the port promises.
 * It lets us exercise the use case with zero database — which is the whole point of
 * depending on the interface rather than Eloquent.
 */
final class InMemoryCandidatureRepository implements CandidatureRepository
{
    /** @var array<string, Candidature> keyed by normalized email */
    private array $byEmail = [];

    /** @var array<string, Candidature> keyed by id */
    private array $byId = [];

    public function nextIdentity(): CandidatureId
    {
        return new CandidatureId((string) Str::ulid());
    }

    public function findById(CandidatureId $id): ?Candidature
    {
        return $this->byId[$id->value()] ?? null;
    }

    public function existsByEmail(Email $email): bool
    {
        return isset($this->byEmail[$email->value()]);
    }

    public function save(Candidature $candidature): void
    {
        $key = $candidature->email()->value();

        if (isset($this->byEmail[$key])) {
            throw new CandidatureAlreadyExists($candidature->email());
        }

        $this->byEmail[$key] = $candidature;
        $this->byId[$candidature->id()->value()] = $candidature;
    }

    public function count(): int
    {
        return count($this->byEmail);
    }
}
