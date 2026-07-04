<?php

declare(strict_types=1);

namespace App\Assignment\Infrastructure\Persistence;

use App\Assignment\Domain\Assignment;
use App\Assignment\Domain\AssignmentRepository;
use App\Assignment\Infrastructure\Cache\ConsolidatedListingCache;
use App\Candidature\Domain\ValueObject\CandidatureId;

/**
 * Caching decorator over the assignment repository: after persisting an assignment it bumps the
 * consolidated-listing cache version, so the next listing read reflects the change. This is the
 * single write path for assignments, so every assigner (single or bulk) invalidates for free.
 */
final readonly class CachingAssignmentRepository implements AssignmentRepository
{
    public function __construct(
        private AssignmentRepository $inner,
        private ConsolidatedListingCache $cache,
    ) {}

    public function save(Assignment $assignment): void
    {
        $this->inner->save($assignment);
        $this->cache->invalidate();
    }

    public function findByCandidature(CandidatureId $candidatureId): ?Assignment
    {
        return $this->inner->findByCandidature($candidatureId);
    }
}
