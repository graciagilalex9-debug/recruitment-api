<?php

declare(strict_types=1);

namespace App\Evaluator\Domain;

use App\Evaluator\Domain\ValueObject\EvaluatorId;

/**
 * The domain's port for persisting and loading evaluators. The Eloquent implementation
 * lives in Infrastructure.
 */
interface EvaluatorRepository
{
    /** Generate a fresh identity for a new evaluator (infrastructure supplies the ULID). */
    public function nextIdentity(): EvaluatorId;

    /** Load an evaluator by id, or null if none exists. */
    public function findById(EvaluatorId $id): ?Evaluator;

    public function save(Evaluator $evaluator): void;
}
