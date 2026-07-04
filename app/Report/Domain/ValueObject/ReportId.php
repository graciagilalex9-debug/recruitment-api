<?php

declare(strict_types=1);

namespace App\Report\Domain\ValueObject;

use App\Report\Domain\Exception\InvalidReportId;

/**
 * ULID identity of a report.
 *
 * Pure domain value object: it only validates and holds a canonical ULID string.
 * Generation of new ids is an infrastructure concern (the repository's nextIdentity()),
 * so this class stays free of any Laravel dependency.
 */
final readonly class ReportId
{
    /** Crockford base32 alphabet (excludes I, L, O, U), 26 chars. */
    private const PATTERN = '/^[0-9A-HJKMNP-TV-Z]{26}$/i';

    private string $value;

    public function __construct(string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidReportId($value);
        }

        $this->value = strtoupper($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
