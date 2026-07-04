<?php

declare(strict_types=1);

namespace App\Evaluator\Domain\ValueObject;

use App\Evaluator\Domain\Exception\InvalidEvaluatorName;

/**
 * Evaluator's name — a non-empty string (trimmed).
 */
final readonly class EvaluatorName
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new InvalidEvaluatorName;
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
