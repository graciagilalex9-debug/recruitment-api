<?php

declare(strict_types=1);

namespace App\Evaluator\Domain\ValueObject;

use App\Evaluator\Domain\Exception\InvalidEvaluatorId;

/**
 * ULID identity of an evaluator. Pure domain value object (generation lives in the
 * repository port, keeping this class Laravel-free).
 */
final readonly class EvaluatorId
{
    /** Crockford base32 alphabet (excludes I, L, O, U), 26 chars. */
    private const PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    private string $value;

    public function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidEvaluatorId($value);
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
