<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Exception;

use DomainException;

final class InvalidCv extends DomainException
{
    public function __construct()
    {
        parent::__construct('CV cannot be empty.');
    }
}
