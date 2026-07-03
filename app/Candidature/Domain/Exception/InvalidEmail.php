<?php

declare(strict_types=1);

namespace App\Candidature\Domain\Exception;

use DomainException;

final class InvalidEmail extends DomainException
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('"%s" is not a valid email address.', $value));
    }
}
