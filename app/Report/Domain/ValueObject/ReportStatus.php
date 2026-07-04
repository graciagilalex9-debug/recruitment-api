<?php

declare(strict_types=1);

namespace App\Report\Domain\ValueObject;

/**
 * The lifecycle status of a report. A closed set with well-defined transitions
 * (pending → processing → completed | failed), so a native backed enum is the right model.
 * completed and failed are terminal. The transition rules live in the Report aggregate.
 */
enum ReportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
