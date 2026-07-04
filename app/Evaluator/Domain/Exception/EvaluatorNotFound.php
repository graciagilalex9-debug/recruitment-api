<?php

declare(strict_types=1);

namespace App\Evaluator\Domain\Exception;

use App\Evaluator\Domain\ValueObject\EvaluatorId;
use DomainException;

final class EvaluatorNotFound extends DomainException
{
    public function __construct(EvaluatorId $id)
    {
        parent::__construct(sprintf('Evaluator %s not found.', $id->value()));
    }
}
