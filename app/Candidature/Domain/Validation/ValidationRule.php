<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Validation;

use App\Candidature\Domain\Candidature;

/**
 * An eligibility rule. Each rule evaluates a candidature and returns its own result.
 * Adding a new rule means adding a new class implementing this interface — existing
 * rules are never modified (open/closed).
 */
interface ValidationRule
{
    public function evaluate(Candidature $candidature): RuleResult;
}
