<?php

declare(strict_types=1);

namespace App\Report\Application\Request;

/**
 * Result of requesting a report: the new report's id and its (initial) status. Plain
 * primitives so the HTTP layer can shape the 202 response without touching the domain.
 */
final readonly class RequestReportResponse
{
    public function __construct(
        public string $id,
        public string $status,
    ) {}
}
