<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Validation\Rule;

use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\Validation\RuleResult;
use App\Candidature\Domain\Validation\ValidationRule;

/**
 * Registration already guarantees a non-empty CV (the Cv value object), so this rule
 * always passes for a stored candidature. It is kept because the PDF lists it as a
 * criterion and it demonstrates the extensible rule set.
 */
final class MustHaveCv implements ValidationRule
{
    public function evaluate(Candidature $candidature): RuleResult
    {
        if ($candidature->cv()->value() !== '') {
            return RuleResult::passed('has_cv', 'The candidature has a CV.');
        }

        return RuleResult::failed('has_cv', 'The candidature must include a CV.');
    }
}
