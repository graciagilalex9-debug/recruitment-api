<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Exception;

use App\Candidature\Domain\ValueObject\CandidatureId;
use DomainException;

final class CandidatureNotFound extends DomainException
{
    public function __construct(CandidatureId $id)
    {
        parent::__construct(sprintf('Candidature %s not found.', $id->value()));
    }
}
