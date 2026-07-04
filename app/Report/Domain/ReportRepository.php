<?php

declare(strict_types=1);

namespace App\Report\Domain;

use App\Report\Domain\ValueObject\ReportId;

/**
 * The domain's port for persisting reports. The Eloquent implementation lives in
 * Infrastructure. `save` upserts by id so the same report can be persisted repeatedly as it
 * moves through its lifecycle (requested → processing → completed/failed).
 */
interface ReportRepository
{
    /** Generate a fresh identity for a new report (infrastructure supplies the ULID). */
    public function nextIdentity(): ReportId;

    /** Persist a report (insert on first save, update on later lifecycle transitions). */
    public function save(Report $report): void;

    /** Load a report by id, or null if none exists. */
    public function find(ReportId $id): ?Report;
}
