<?php

declare(strict_types=1);

namespace App\Report\Domain\Exception;

use DomainException;

final class InvalidReportId extends DomainException
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('"%s" is not a valid ULID report id.', $value));
    }
}
