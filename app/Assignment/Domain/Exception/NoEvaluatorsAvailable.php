<?php

declare(strict_types=1);

namespace App\Assignment\Domain\Exception;

use DomainException;

final class NoEvaluatorsAvailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('No evaluators available to assign candidatures to.');
    }
}
