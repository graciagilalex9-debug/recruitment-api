<?php

declare(strict_types=1);

namespace App\Candidature\Application\Validate;

use App\Candidature\Domain\Validation\RuleResult;
use App\Candidature\Domain\Validation\ValidationReport;

/**
 * Output DTO for the validation use case: primitives the HTTP layer can serialize.
 * Maps the domain ValidationReport into a neutral, framework-agnostic shape once.
 */
final readonly class ValidationReportResponse
{
    /**
     * @param  list<RuleResultResponse>  $rules
     */
    public function __construct(
        public string $candidatureId,
        public bool $valid,
        public array $rules,
    ) {}

    public static function fromReport(string $candidatureId, ValidationReport $report): self
    {
        $rules = array_map(
            static fn (RuleResult $result): RuleResultResponse => new RuleResultResponse(
                $result->key(),
                $result->hasPassed(),
                $result->reason(),
            ),
            $report->results(),
        );

        return new self($candidatureId, $report->isValid(), $rules);
    }
}
