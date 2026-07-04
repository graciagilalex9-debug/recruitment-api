<?php

declare(strict_types=1);

namespace App\Report\Domain\ValueObject;

/**
 * A snapshot of the listing parameters a report was requested with: the sort column, the
 * direction, and the per-column filters. Kept as a value object so the report records
 * exactly what it was asked to produce (the same sort/filter semantics as the consolidated
 * listing). Validation of the allowed columns happens at the HTTP boundary (FormRequest)
 * and in the reader whitelist; here it is a faithful, immutable record.
 */
final readonly class ReportCriteria
{
    /** @var array<string, string> column => value */
    private array $filters;

    /**
     * @param  array<string, string>  $filters
     */
    public function __construct(
        private string $sort,
        private string $direction,
        array $filters,
    ) {
        $this->filters = $filters;
    }

    public function sort(): string
    {
        return $this->sort;
    }

    public function direction(): string
    {
        return $this->direction;
    }

    /**
     * @return array<string, string>
     */
    public function filters(): array
    {
        return $this->filters;
    }
}
