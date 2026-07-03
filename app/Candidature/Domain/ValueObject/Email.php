<?php

declare(strict_types=1);

namespace App\Candidature\Domain\ValueObject;

use App\Candidature\Domain\Exception\InvalidEmail;

/**
 * Email address of a candidature — also its business identity.
 *
 * Normalization (trim + lowercase) happens here, so every Email in the system is
 * canonical. That is what makes uniqueness case-insensitive: two addresses differing
 * only in case collapse to the same value, so equals() is true and the stored string
 * is identical. Format is validated with native PHP (no framework dependency).
 */
final readonly class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidEmail($value);
        }

        $this->value = $normalized;
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
