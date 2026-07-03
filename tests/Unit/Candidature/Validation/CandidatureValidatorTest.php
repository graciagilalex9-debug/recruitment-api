<?php

declare(strict_types=1);

namespace Tests\Unit\Candidature\Validation;

use App\Candidature\Domain\Validation\CandidatureValidator;
use App\Candidature\Domain\Validation\Rule\MustHaveCv;
use App\Candidature\Domain\Validation\Rule\MustHaveMinimumExperience;
use App\Candidature\Domain\Validation\Rule\MustHaveValidEmail;
use PHPUnit\Framework\TestCase;
use Tests\Support\CandidatureMother;

final class CandidatureValidatorTest extends TestCase
{
    private function validator(): CandidatureValidator
    {
        return new CandidatureValidator([
            new MustHaveCv,
            new MustHaveValidEmail,
            new MustHaveMinimumExperience,
        ]);
    }

    public function test_report_is_valid_when_every_rule_passes(): void
    {
        $report = $this->validator()->validate(CandidatureMother::withYearsOfExperience(5));

        $this->assertTrue($report->isValid());
        $this->assertCount(3, $report->results());
    }

    public function test_report_is_invalid_when_a_rule_fails(): void
    {
        $report = $this->validator()->validate(CandidatureMother::withYearsOfExperience(1));

        $this->assertFalse($report->isValid());
        $this->assertCount(3, $report->results());
    }
}
