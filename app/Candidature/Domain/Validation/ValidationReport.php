<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Validation;

/**
 * The aggregated result of running every validation rule against a candidature.
 * Valid only when all rules passed.
 */
final readonly class ValidationReport
{
    /**
     * @param  list<RuleResult>  $results
     */
    public function __construct(private array $results) {}

    public function isValid(): bool
    {
        return array_all($this->results, static fn (RuleResult $result): bool => $result->hasPassed());
    }

    /**
     * @return list<RuleResult>
     */
    public function results(): array
    {
        return $this->results;
    }
}
