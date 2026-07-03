<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Validation;

/**
 * The outcome of a single validation rule: its key, whether it passed, and a
 * human-readable reason. Immutable; built through the passed()/failed() named constructors.
 */
final readonly class RuleResult
{
    private function __construct(
        private string $key,
        private bool $passed,
        private string $reason,
    ) {}

    public static function passed(string $key, string $reason): self
    {
        return new self($key, true, $reason);
    }

    public static function failed(string $key, string $reason): self
    {
        return new self($key, false, $reason);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function hasPassed(): bool
    {
        return $this->passed;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
