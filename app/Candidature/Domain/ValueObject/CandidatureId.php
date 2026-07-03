<?php

declare(strict_types=1);

namespace App\Candidature\Domain\ValueObject;

use App\Candidature\Domain\Exception\InvalidCandidatureId;

/**
 * ULID identity of a candidature.
 *
 * Pure domain value object: it only validates and holds a canonical ULID string.
 * Generation of new ids is an infrastructure concern (the repository's nextIdentity(),
 * which uses the framework), so this class stays free of any Laravel dependency.
 */
final readonly class CandidatureId
{
    /** Crockford base32 alphabet (excludes I, L, O, U), 26 chars. */
    private const PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    private string $value;

    public function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidCandidatureId($value);
        }

        $this->value = strtoupper($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
