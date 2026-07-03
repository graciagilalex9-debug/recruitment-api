<?php

declare(strict_types=1);

namespace Tests\Unit\Candidature\Validation;

use App\Candidature\Domain\Validation\Rule\MustHaveCv;
use App\Candidature\Domain\Validation\Rule\MustHaveMinimumExperience;
use App\Candidature\Domain\Validation\Rule\MustHaveValidEmail;
use PHPUnit\Framework\TestCase;
use Tests\Support\CandidatureMother;

final class ValidationRulesTest extends TestCase
{
    public function test_minimum_experience_passes_at_two_years(): void
    {
        $result = (new MustHaveMinimumExperience)->evaluate(CandidatureMother::withYearsOfExperience(2));

        $this->assertTrue($result->hasPassed());
        $this->assertSame('minimum_experience', $result->key());
    }

    public function test_minimum_experience_fails_below_two_years(): void
    {
        $result = (new MustHaveMinimumExperience)->evaluate(CandidatureMother::withYearsOfExperience(1));

        $this->assertFalse($result->hasPassed());
        $this->assertSame('minimum_experience', $result->key());
        $this->assertStringContainsString('2 years', $result->reason());
    }

    public function test_cv_and_email_rules_pass_for_a_valid_candidature(): void
    {
        $candidature = CandidatureMother::withYearsOfExperience(5);

        $this->assertTrue((new MustHaveCv)->evaluate($candidature)->hasPassed());
        $this->assertTrue((new MustHaveValidEmail)->evaluate($candidature)->hasPassed());
    }
}
