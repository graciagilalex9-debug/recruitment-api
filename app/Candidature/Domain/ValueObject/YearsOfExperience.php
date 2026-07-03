<?php

declare(strict_types=1);

namespace App\Candidature\Domain\ValueObject;

use App\Candidature\Domain\Exception\InvalidYearsOfExperience;

/**
 * Years of professional experience — a non-negative integer.
 */
final readonly class YearsOfExperience
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 0) {
            throw new InvalidYearsOfExperience($value);
        }

        $this->value = $value;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
