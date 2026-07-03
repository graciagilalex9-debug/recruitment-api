<?php

declare(strict_types=1);

namespace App\Candidature\Domain\ValueObject;

use App\Candidature\Domain\Exception\InvalidFullName;

/**
 * Candidate's full name — a non-empty string (trimmed).
 */
final readonly class FullName
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidFullName;
        }

        $this->value = $trimmed;
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
