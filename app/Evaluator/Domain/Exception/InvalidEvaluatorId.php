<?php

declare(strict_types=1);

namespace App\Evaluator\Domain\Exception;

use DomainException;

final class InvalidEvaluatorId extends DomainException
{
    public function __construct(string $value)
    {
        parent::__construct(sprintf('"%s" is not a valid ULID evaluator id.', $value));
    }
}
