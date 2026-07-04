<?php

declare(strict_types=1);

namespace App\Report\Domain\Exception;

use App\Report\Domain\ValueObject\ReportStatus;
use DomainException;

/**
 * Raised when the report lifecycle is driven through an illegal transition
 * (e.g. completing a report that is not being processed). It signals a programming error,
 * not a client error, so it is not mapped to an HTTP status.
 */
final class InvalidReportTransition extends DomainException
{
    public function __construct(ReportStatus $from, ReportStatus $to)
    {
        parent::__construct(sprintf(
            'Cannot transition report from "%s" to "%s".',
            $from->value,
            $to->value,
        ));
    }
}
