# Tasks — add-excel-report

Capability: **excel-report**. A new `Report` bounded context (stateful aggregate) plus the app's first
queue/worker + email path. Reuses the `consolidated-listing` read model via a new streaming read port
in the Assignment context. Every scenario in the delta is covered by a test in §9.

## 1. Pre-work — dependency and environment
- [x] 1.1 `composer require phpoffice/phpspreadsheet` (inside the container); commit the lock.
- [x] 1.2 Verify env/config: `QUEUE_CONNECTION=redis`, mail → Mailpit, `local` filesystem disk; the
  `worker` service runs `queue:work redis --tries=3 --backoff=5`. Add `.env.example` keys if missing.

## 2. Domain — the `Report` aggregate (pure PHP, states)
- [x] 2.1 `Report/Domain/ValueObject/ReportId` — ULID VO (validate format; no generation here).
- [x] 2.2 `Report/Domain/ValueObject/ReportType` — backed enum (`CONSOLIDATED_LISTING`).
- [x] 2.3 `Report/Domain/ValueObject/ReportStatus` — backed enum
  (`PENDING|PROCESSING|COMPLETED|FAILED`).
- [x] 2.4 `Report/Domain/ValueObject/ReportCriteria` — VO snapshot of `sort`, `direction`,
  `filters` (the params the report was requested with).
- [x] 2.5 `Report/Domain/Report` — aggregate root. Named constructors `request(id, type, criteria)`
  (→ `PENDING`, `requestedAt`) and `reconstitute(...)`. Guarded transitions `markProcessing()`,
  `markCompleted(filePath)`, `markFailed(reason)` (illegal move → `InvalidReportTransition`);
  accessors for id/type/status/criteria/filePath/timestamps/failureReason.
- [x] 2.6 `Report/Domain/Exception/{ReportNotFound, InvalidReportTransition}`.
- [x] 2.7 `Report/Domain/ReportRepository` — port: `nextIdentity(): ReportId`, `save(Report): void`,
  `find(ReportId): ?Report`.

## 3. Application — use cases and ports (framework-agnostic)
- [x] 3.1 `Report/Application/ReportDispatcher` — port `dispatch(ReportId): void` (enqueue).
- [x] 3.2 `Report/Application/Request/RequestReportResponse` — DTO: `id`, `status`.
- [x] 3.3 `Report/Application/Request/RequestReport` — build `ReportCriteria`, `repo.nextIdentity`,
  `Report::request`, `repo.save`, `dispatcher.dispatch(id)`; return `RequestReportResponse` (pending).
- [x] 3.4 `Report/Application/Generate/ConsolidatedReportWriter` — port
  `write(ReportId, iterable<ConsolidatedRow>): string` (returns the stored path).
- [x] 3.5 `Report/Application/Generate/ReportNotifier` — port `notifyReady(Report): void`.
- [x] 3.6 `Report/Application/Generate/GenerateReport` — `repo.find` (→ `ReportNotFound`),
  `markProcessing`+save, stream rows via the Assignment stream reader, `writer.write`,
  `markCompleted`+save, `notifier.notifyReady`.
- [x] 3.7 `Report/Application/Fail/MarkReportFailed` — `repo.find`, `markFailed(reason)`, save.

## 4. Assignment — streaming read port (reuse the listing)
- [x] 4.1 `Assignment/Application/Consolidated/ConsolidatedListingStreamReader` — port
  `stream(ConsolidatedListingQuery): iterable<ConsolidatedRow>` (all matching rows, no pagination).
- [x] 4.2 `Assignment/Infrastructure/Persistence/QueryBuilderConsolidatedListingStreamReader` — same
  JOIN + `joinSub` + whitelisted filters + sort as the paginated reader, **no LIMIT**, iterate with
  `->cursor()` and `yield` a `ConsolidatedRow` per row.

## 5. Infrastructure — persistence
- [x] 5.1 Migration `reports`: `id` (ULID, PK), `type`, `status`, `sort`, `direction`,
  `filters` (json), `file_path` (nullable), `failure_reason` (nullable text), `requested_at`,
  `completed_at` (nullable), timestamps.
- [x] 5.2 `Report/Infrastructure/Persistence/ReportModel` — Eloquent, string ULID PK
  (non-incrementing); `filters` cast to array. NOT the domain entity.
- [x] 5.3 `Report/Infrastructure/Persistence/ReportMapper` — model ↔ aggregate (enums, criteria json).
- [x] 5.4 `Report/Infrastructure/Persistence/EloquentReportRepository` — `nextIdentity` (`Str::ulid`),
  `save` (upsert), `find`.

## 6. Infrastructure — queue, spreadsheet, mail
- [x] 6.1 `Report/Infrastructure/Queue/GenerateReportJob` — `ShouldQueue`; ctor takes the report id
  string; `handle(GenerateReport)` runs it; `failed(Throwable)` runs `MarkReportFailed`. `afterCommit`.
- [x] 6.2 `Report/Infrastructure/Queue/LaravelReportDispatcher` — `ReportDispatcher` via
  `GenerateReportJob::dispatch($id->value())`.
- [x] 6.3 `Report/Infrastructure/Report/PhpSpreadsheetConsolidatedReportWriter` —
  `ConsolidatedReportWriter`: iterate rows, start a new sheet every 50 (header row per sheet), write
  the `.xlsx` to the `local` disk under `reports/{id}.xlsx`, return the path.
- [x] 6.4 `Report/Infrastructure/Mail/ReportReadyMail` — Mailable with the download link.
- [x] 6.5 `Report/Infrastructure/Mail/MailReportNotifier` — `ReportNotifier`: build the download URL
  (`route('reports.download', id)`), send `ReportReadyMail` via the `Mail` facade.

## 7. Infrastructure — HTTP
- [x] 7.1 `Report/Infrastructure/Http/ExportConsolidatedReportRequest` (FormRequest) — `sort` in the
  listing's sortable whitelist, `direction` in `asc,desc`; filters optional (whitelisted keys).
- [x] 7.2 `Report/Infrastructure/Http/PostConsolidatedReportController` — build the query/criteria
  from validated input, call `RequestReport`, return the resource with `202`.
- [x] 7.3 `Report/Infrastructure/Http/GetReportController` — `repo.find`; `404` if missing; else `200`
  resource.
- [x] 7.4 `Report/Infrastructure/Http/DownloadReportController` — `404` if missing; `409` if not
  `completed`; else stream the stored `.xlsx` with the correct content type.
- [x] 7.5 `Report/Infrastructure/Http/ReportResource` — `id`, `type`, `status`, `requested_at`,
  `completed_at`, `download_url` (when completed), `failure_reason` (when failed).
- [x] 7.6 Routes in `routes/api.php`: `POST /candidatures/consolidated/export`, `GET /reports/{id}`
  (whereUlid), `GET /reports/{id}/download` (whereUlid, name `reports.download`).

## 8. DI wiring
- [x] 8.1 `Report/Infrastructure/Providers/ReportServiceProvider` — bind `ReportRepository`,
  `ReportDispatcher`, `ConsolidatedReportWriter`, `ReportNotifier` to their implementations; register
  it in `bootstrap/providers.php`.
- [x] 8.2 Bind `ConsolidatedListingStreamReader` → `QueryBuilderConsolidatedListingStreamReader` in
  `AssignmentServiceProvider`.

## 9. Tests (real mysql-test; `sync` queue; `Mail`/`Storage` faked)
- [x] 9.1 Unit — `Report` lifecycle: `request()` → `PENDING`; `markProcessing`→`markCompleted` path;
  illegal transition throws `InvalidReportTransition`. *(covers: async generation completes; failure)*
- [x] 9.2 Feature — `POST /candidatures/consolidated/export` → `202` with a report id and `pending`.
  *(covers: export request accepted)*
- [x] 9.3 Feature — invalid `sort` on `POST /export` → `422`. *(covers: invalid export request)*
- [x] 9.4 Feature (sync) — after export, `GET /reports/{id}` → `completed` with a `download_url`, and
  `GET /reports/{id}/download` → `200` with the xlsx content type. *(covers: generation completes;
  status of existing report; download of a completed report)*
- [x] 9.5 Feature (sync) — seed > 50 assigned candidatures; the downloaded workbook has sheets of at
  most 50 rows. *(covers: sheets of at most 50)*
- [x] 9.6 Feature (sync) — export with a filter; the workbook contains only matching candidatures.
  *(covers: export honours filters)*
- [x] 9.7 Feature — `GET /reports/{id}` for an unknown id → `404`. *(covers: status of unknown report)*
- [x] 9.8 Feature — `GET /reports/{id}/download` before completion → `409`. *(covers: download not
  ready)*
- [x] 9.9 Feature (sync) — completion sends the email with the link (`Mail::fake()`). *(covers:
  email notification)*
- [x] 9.10 Feature (sync) — a writer bound to a throwing fake drives the report to `failed` with a
  reason (`GET /reports/{id}` shows `failed`). *(covers: failed generation reflected)*

## 10. API contract, docs and cleanup
- [x] 10.1 Add the three routes to `docs/openapi.yaml` (`202`/`422`, `200`/`404`, `200`/`409`/`404`).
- [x] 10.2 `http/reports.http` — export (with/without filter), poll status, download; plus an invalid
  export (422) and unknown-id (404).
- [x] 10.3 `docs/scalability-backlog.md` — add the §7 deferrals (streaming writer/cursor, object
  storage + signed URLs, idempotent re-requests, failure notification, download authorization).
- [x] 10.4 README: document the export endpoints + the queue/worker/email flow; roadmap update.
- [x] 10.5 `make quality` (Pint + PHPStan L8 + tests) green.
