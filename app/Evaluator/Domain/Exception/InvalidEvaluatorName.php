<?php

declare(strict_types=1);

namespace App\Evaluator\Domain\Exception;

use DomainException;

final class InvalidEvaluatorName extends DomainException
{
    public function __construct()
    {
        parent::__construct('Evaluator name cannot be empty.');
    }
}
