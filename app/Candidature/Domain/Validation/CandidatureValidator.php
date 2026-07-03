<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Validation;

use App\Candidature\Domain\Candidature;

/**
 * Runs the ordered set of validation rules against a candidature and collects every
 * result into a report. Stateless — a single instance is reused across requests.
 * The rule set is injected, so adding a rule never touches this class (open/closed).
 */
final readonly class CandidatureValidator
{
    /**
     * @param  list<ValidationRule>  $rules
     */
    public function __construct(private array $rules) {}

    public function validate(Candidature $candidature): ValidationReport
    {
        $results = array_map(
            static fn (ValidationRule $rule): RuleResult => $rule->evaluate($candidature),
            $this->rules,
        );

        return new ValidationReport($results);
    }
}
