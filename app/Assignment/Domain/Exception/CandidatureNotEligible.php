<?php

declare(strict_types=1);

namespace App\Assignment\Domain\Exception;

use App\Candidature\Domain\ValueObject\CandidatureId;
use DomainException;

final class CandidatureNotEligible extends DomainException
{
    public function __construct(CandidatureId $id)
    {
        parent::__construct(sprintf('Candidature %s is not eligible for assignment.', $id->value()));
    }
}
