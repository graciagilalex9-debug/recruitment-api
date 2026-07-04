<?php

declare(strict_types=1);

namespace App\Evaluator\Application\Register;

/**
 * Input DTO for creating an evaluator: raw primitives from the outside world.
 */
final readonly class RegisterEvaluatorCommand
{
    public function __construct(
        public string $name,
    ) {}
}
