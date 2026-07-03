<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Exception;

use App\Candidature\Domain\ValueObject\Email;
use DomainException;

final class CandidatureAlreadyExists extends DomainException
{
    public function __construct(Email $email)
    {
        parent::__construct(sprintf('A candidature with email %s already exists.', $email->value()));
    }
}
