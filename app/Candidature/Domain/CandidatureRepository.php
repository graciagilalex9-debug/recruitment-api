<?php

declare(strict_types=1);

namespace App\Candidature\Domain;

use App\Candidature\Domain\Exception\CandidatureAlreadyExists;
use App\Candidature\Domain\ValueObject\CandidatureId;
use App\Candidature\Domain\ValueObject\Email;

/**
 * The domain's port for persisting candidatures.
 *
 * The domain declares what it needs (a new identity, an existence check, a way to save)
 * without knowing how it is done. The Eloquent implementation lives in Infrastructure.
 * This inversion is what lets us swap the data layer without touching business logic.
 */
interface CandidatureRepository
{
    /** Generate a fresh identity for a new candidature (infrastructure supplies the ULID). */
    public function nextIdentity(): CandidatureId;

    /** Whether a candidature already exists for the given (normalized) email. */
    public function existsByEmail(Email $email): bool;

    /**
     * Persist a candidature.
     *
     * @throws CandidatureAlreadyExists if the email is already taken (the unique constraint is the
     *                                  race-safe guarantee — see the infrastructure implementation).
     */
    public function save(Candidature $candidature): void;
}
