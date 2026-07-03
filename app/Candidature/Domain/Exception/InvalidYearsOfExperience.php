<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Exception;

use DomainException;

final class InvalidYearsOfExperience extends DomainException
{
    public function __construct(int $value)
    {
        parent::__construct(sprintf('Years of experience cannot be negative, got %d.', $value));
    }
}
