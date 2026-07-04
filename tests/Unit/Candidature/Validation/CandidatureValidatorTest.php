<?php

declare(strict_types=1);

namespace Tests\Unit\Candidature\Validation;

use App\Candidature\Domain\Candidature;
use App\Candidature\Domain\Validation\CandidatureValidator;
use App\Candidature\Domain\Validation\Rule\MustHaveCv;
use App\Candidature\Domain\Validation\Rule\MustHaveMinimumExperience;
use App\Candidature\Domain\Validation\Rule\MustHaveValidEmail;
use App\Candidature\Domain\Validation\RuleResult;
use App\Candidature\Domain\Validation\ValidationRule;
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

    /**
     * This is the behaviour that justifies choosing a rule pipeline over a classic Chain of
     * Responsibility: a failing rule does NOT short-circuit — every later rule still runs.
     */
    public function test_a_failing_rule_does_not_stop_later_rules_from_being_evaluated(): void
    {
        $failing = new class implements ValidationRule
        {
            public function evaluate(Candidature $candidature): RuleResult
            {
                return RuleResult::failed('always_fails', 'This rule always fails.');
            }
        };

        $spy = new class implements ValidationRule
        {
            public bool $evaluated = false;

            public function evaluate(Candidature $candidature): RuleResult
            {
                $this->evaluated = true;

                return RuleResult::passed('spy', 'Ran after the failing rule.');
            }
        };

        $report = (new CandidatureValidator([$failing, $spy]))
            ->validate(CandidatureMother::withYearsOfExperience(5));

        $this->assertTrue($spy->evaluated, 'The rule after a failing one must still be evaluated.');
        $this->assertFalse($report->isValid());
        $this->assertCount(2, $report->results());
    }

    public function test_the_report_collects_every_failure_not_just_the_first(): void
    {
        $firstFail = new class implements ValidationRule
        {
            public function evaluate(Candidature $candidature): RuleResult
            {
                return RuleResult::failed('first', 'First failure.');
            }
        };

        $secondFail = new class implements ValidationRule
        {
            public function evaluate(Candidature $candidature): RuleResult
            {
                return RuleResult::failed('second', 'Second failure.');
            }
        };

        $report = (new CandidatureValidator([$firstFail, $secondFail]))
            ->validate(CandidatureMother::withYearsOfExperience(5));

        $failedKeys = array_values(array_map(
            static fn (RuleResult $result): string => $result->key(),
            array_filter($report->results(), static fn (RuleResult $result): bool => ! $result->hasPassed()),
        ));

        $this->assertEqualsCanonicalizing(['first', 'second'], $failedKeys);
    }
}
