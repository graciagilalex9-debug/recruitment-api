<?php

declare(strict_types=1);

namespace App\Report\Domain;

use App\Report\Domain\Exception\InvalidReportTransition;
use App\Report\Domain\ValueObject\ReportCriteria;
use App\Report\Domain\ValueObject\ReportId;
use App\Report\Domain\ValueObject\ReportStatus;
use App\Report\Domain\ValueObject\ReportType;
use DateTimeImmutable;

/**
 * A report generation request and its lifecycle.
 *
 * This is the app's first STATEFUL aggregate: unlike the other (immutable) aggregates, a
 * report moves through states — pending → processing → completed | failed. The transitions
 * are guarded here (the domain owns the rules); driving an illegal one throws
 * InvalidReportTransition. completed and failed are terminal.
 *
 * Timestamps are passed in (not read from a clock inside the domain) to keep the domain
 * pure and deterministically testable.
 */
final class Report
{
    private function __construct(
        private readonly ReportId $id,
        private readonly ReportType $type,
        private readonly ReportCriteria $criteria,
        private ReportStatus $status,
        private readonly DateTimeImmutable $requestedAt,
        private ?string $filePath,
        private ?DateTimeImmutable $completedAt,
        private ?string $failureReason,
    ) {}

    /** A freshly requested report: pending, nothing produced yet. */
    public static function request(
        ReportId $id,
        ReportType $type,
        ReportCriteria $criteria,
        DateTimeImmutable $requestedAt,
    ): self {
        return new self(
            id: $id,
            type: $type,
            criteria: $criteria,
            status: ReportStatus::Pending,
            requestedAt: $requestedAt,
            filePath: null,
            completedAt: null,
            failureReason: null,
        );
    }

    /** Rebuild an existing report from storage (no transition rules applied). */
    public static function reconstitute(
        ReportId $id,
        ReportType $type,
        ReportCriteria $criteria,
        ReportStatus $status,
        DateTimeImmutable $requestedAt,
        ?string $filePath,
        ?DateTimeImmutable $completedAt,
        ?string $failureReason,
    ): self {
        return new self(
            id: $id,
            type: $type,
            criteria: $criteria,
            status: $status,
            requestedAt: $requestedAt,
            filePath: $filePath,
            completedAt: $completedAt,
            failureReason: $failureReason,
        );
    }

    /** pending → processing (the worker started building the file). */
    public function markProcessing(): void
    {
        if ($this->status !== ReportStatus::Pending) {
            throw new InvalidReportTransition($this->status, ReportStatus::Processing);
        }

        $this->status = ReportStatus::Processing;
    }

    /** processing → completed (the file is stored and downloadable). */
    public function markCompleted(string $filePath, DateTimeImmutable $completedAt): void
    {
        if ($this->status !== ReportStatus::Processing) {
            throw new InvalidReportTransition($this->status, ReportStatus::Completed);
        }

        $this->status = ReportStatus::Completed;
        $this->filePath = $filePath;
        $this->completedAt = $completedAt;
    }

    /** pending|processing → failed (generation could not complete). */
    public function markFailed(string $reason): void
    {
        if ($this->status->isTerminal()) {
            throw new InvalidReportTransition($this->status, ReportStatus::Failed);
        }

        $this->status = ReportStatus::Failed;
        $this->failureReason = $reason;
    }

    public function isCompleted(): bool
    {
        return $this->status === ReportStatus::Completed;
    }

    public function id(): ReportId
    {
        return $this->id;
    }

    public function type(): ReportType
    {
        return $this->type;
    }

    public function criteria(): ReportCriteria
    {
        return $this->criteria;
    }

    public function status(): ReportStatus
    {
        return $this->status;
    }

    public function requestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function filePath(): ?string
    {
        return $this->filePath;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }
}
