<?php

declare(strict_types=1);

namespace App\Candidature\Domain\ValueObject;

use App\Candidature\Domain\Exception\InvalidCv;

/**
 * Candidate's CV as free text — a non-empty string (trimmed).
 */
final readonly class Cv
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidCv;
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
