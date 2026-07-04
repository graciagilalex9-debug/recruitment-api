<?php

declare(strict_types=1);

namespace App\Report\Domain\Exception;

use App\Report\Domain\ValueObject\ReportId;
use DomainException;

final class ReportNotFound extends DomainException
{
    public function __construct(ReportId $id)
    {
        parent::__construct(sprintf('Report %s not found.', $id->value()));
    }
}
