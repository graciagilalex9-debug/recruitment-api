<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Validation\Rule;

use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\Validation\RuleResult;
use App\Candidature\Domain\Validation\ValidationRule;

/**
 * The Email value object already guarantees a well-formed address, so this rule always
 * passes for a stored candidature. Kept per the PDF's criteria and to demonstrate the
 * rule set; the defensive re-check keeps the rule meaningful on its own.
 */
final class MustHaveValidEmail implements ValidationRule
{
    public function evaluate(Candidature $candidature): RuleResult
    {
        if (filter_var($candidature->email()->value(), FILTER_VALIDATE_EMAIL) !== false) {
            return RuleResult::passed('valid_email', 'The email is valid.');
        }

        return RuleResult::failed('valid_email', 'The email is not a valid address.');
    }
}
