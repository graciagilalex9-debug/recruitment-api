<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Exception;

use DomainException;

final class InvalidFullName extends DomainException
{
    public function __construct()
    {
        parent::__construct('Full name cannot be empty.');
    }
}
