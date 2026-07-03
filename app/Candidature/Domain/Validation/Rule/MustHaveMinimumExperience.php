<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Validation\Rule;

use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\Validation\RuleResult;
use App\Candidature\Domain\Validation\ValidationRule;

final class MustHaveMinimumExperience implements ValidationRule
{
    private const MINIMUM_YEARS = 2;

    public function evaluate(Candidature $candidature): RuleResult
    {
        $years = $candidature->yearsOfExperience()->value();

        if ($years >= self::MINIMUM_YEARS) {
            return RuleResult::passed('minimum_experience', 'Has at least 2 years of experience.');
        }

        return RuleResult::failed('minimum_experience', sprintf(
            'Requires at least %d years of experience; has %d.',
            self::MINIMUM_YEARS,
            $years,
        ));
    }
}
