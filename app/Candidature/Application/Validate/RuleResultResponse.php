<?php

declare(strict_types=1);

namespace App\Candidature\Application\Validate;

/**
 * A single rule outcome as framework-agnostic primitives (the domain RuleResult never
 * crosses into the HTTP layer).
 */
final readonly class RuleResultResponse
{
    public function __construct(
        public string $key,
        public bool $passed,
        public string $reason,
    ) {}
}
