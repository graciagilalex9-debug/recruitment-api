# Design — add-excel-report

An asynchronous export: a request records a `Report` and enqueues a job; a worker builds the workbook
from the consolidated listing, stores it, marks the report completed and emails the requester. This is
the app's first **stateful aggregate** (a status lifecycle) and its first **background-processing**
path (Redis queue + worker), so the design leans on ports to keep the domain and application layers
free of Laravel.

## 1. Architecture — the `Report` bounded context

```
app/Report/
├─ Domain/
│  ├─ Report.php                         aggregate root + status lifecycle
│  ├─ ValueObject/
│  │  ├─ ReportId.php                    ULID (validated)
│  │  ├─ ReportType.php                  enum: CONSOLIDATED_LISTING
│  │  ├─ ReportStatus.php                enum: PENDING|PROCESSING|COMPLETED|FAILED
│  │  └─ ReportCriteria.php              snapshot of sort/direction/filters used
│  ├─ ReportRepository.php               port: nextIdentity / save / find(ReportId): ?Report
│  └─ Exception/
│     ├─ ReportNotFound.php
│     └─ InvalidReportTransition.php
├─ Application/
│  ├─ Request/
│  │  ├─ RequestReport.php               create Report(pending), save, enqueue → return id
│  │  └─ RequestReportResponse.php       DTO: id, status
│  ├─ Generate/
│  │  ├─ GenerateReport.php              processing → write workbook → completed → notify
│  │  ├─ ConsolidatedReportWriter.php    port: write(ReportId, iterable<ConsolidatedRow>): string(path)
│  │  └─ ReportNotifier.php              port: notifyReady(Report): void
│  ├─ Fail/
│  │  └─ MarkReportFailed.php            failed → store reason (called by the job's failed() hook)
│  └─ ReportDispatcher.php               port: dispatch(ReportId): void  (enqueue, no Laravel here)
└─ Infrastructure/
   ├─ Persistence/
   │  ├─ ReportModel.php                 Eloquent (ULID PK), NOT the domain entity
   │  ├─ ReportMapper.php                model <-> aggregate
   │  └─ EloquentReportRepository.php
   ├─ Queue/
   │  ├─ GenerateReportJob.php           ShouldQueue; handle()->GenerateReport; failed()->MarkReportFailed
   │  └─ LaravelReportDispatcher.php     ReportDispatcher via GenerateReportJob::dispatch()
   ├─ Report/
   │  └─ PhpSpreadsheetConsolidatedReportWriter.php   50 rows/sheet, store on 'local' disk
   ├─ Mail/
   │  ├─ ReportReadyMail.php             Mailable (download link)
   │  └─ MailReportNotifier.php          ReportNotifier via Mail facade
   ├─ Http/
   │  ├─ ExportConsolidatedReportRequest.php   FormRequest — same sort/direction/filter whitelist
   │  ├─ PostConsolidatedReportController.php   POST .../export → 202
   │  ├─ GetReportController.php                GET /reports/{id}
   │  ├─ DownloadReportController.php           GET /reports/{id}/download
   │  └─ ReportResource.php                     id/type/status/timestamps/download_url/failure_reason
   └─ Providers/ReportServiceProvider.php       bind the ports
```

### Reuse of the consolidated read model (cross-context, read-only)
The workbook rows are exactly the `consolidated-listing` rows, so `Report` consumes the Assignment
read model rather than re-implementing the query. To stream all rows (not a page), the Assignment
context gains a **new read port** next to the paginated one:

```
app/Assignment/Application/Consolidated/
  ConsolidatedListingStreamReader.php     port: stream(ConsolidatedListingQuery): iterable<ConsolidatedRow>
app/Assignment/Infrastructure/Persistence/
  QueryBuilderConsolidatedListingStreamReader.php   same JOIN+joinSub+filters+sort, no LIMIT, ->cursor()
```

`Report`'s `GenerateReport` depends on `ConsolidatedListingStreamReader` and `ConsolidatedRow` — a
deliberate downstream (customer/supplier) relationship: `Report` is a consumer of the
`consolidated-listing` read model. The paginated reader is untouched; the two share the JOIN shape and
the sort/filter whitelist. `ConsolidatedListingQuery.page/perPage` are ignored by the stream (it
returns every matching row); only `sort`, `direction`, `filters` apply.

## 2. The asynchronous flow

```
POST /export ─► ExportConsolidatedReportRequest (validate sort/dir/filter, same whitelist as listing)
             ─► RequestReport: repo.nextIdentity → Report::request(type, criteria) [PENDING] → repo.save
             ─► ReportDispatcher.dispatch(id)   (enqueue GenerateReportJob AFTER commit)
             ─► 202 { report_id, status: "pending" }

worker (queue:work redis) pops GenerateReportJob(id):
   handle() ─► GenerateReport(id):
                repo.find(id) → report.markProcessing() → repo.save
                rows = streamReader.stream(criteria→query)         (cursor, no LIMIT)
                path = writer.write(id, rows)                      (50 rows/sheet → local disk)
                report.markCompleted(path) → repo.save
                notifier.notifyReady(report)                       (email with download link)
   failed(e) ─► MarkReportFailed(id, reason): report.markFailed(reason) → repo.save
```

The job carries only the **report id** (jobs are serialized; never pass aggregates or big data).
Enqueue must happen **after** the DB transaction commits (`afterCommit`) so the worker can find the
row; otherwise a fast worker races the writer. In tests the `sync` queue driver runs `handle()` inline
against the same connection, exercising the whole chain end to end.

## 3. The status lifecycle (first stateful aggregate)

```
        request()
   ── ─► PENDING ──markProcessing()──► PROCESSING ──markCompleted(path)──► COMPLETED
                                            │
                                            └────────markFailed(reason)──► FAILED
```

Transitions are guarded in the domain: calling `markCompleted` on a non-`PROCESSING` report (or any
illegal move) throws `InvalidReportTransition`. `COMPLETED`/`FAILED` are terminal. `ReportStatus` and
`ReportType` are native PHP backed enums — a fixed, closed set is exactly what an enum models, and
enums are pure PHP (no Laravel), so they belong in the domain.

## 4. Stack decisions

| Concern | Pick | Why |
|---|---|---|
| Async contract | `POST` → `202` + report id; `GET /reports/{id}` status; `GET /reports/{id}/download` | Standard async REST; the file is not built inline. |
| Background work | Laravel **queue on Redis** + `queue:work` worker (`--tries=3 --backoff=5`) | Offload slow generation; retries on transient failure. |
| Job payload | the **report id** only | Jobs are serialized; pass identifiers, not objects/data. |
| Enqueue timing | `afterCommit` (dispatch after the DB commit) | The worker must find the persisted `reports` row. |
| Spreadsheet | **PhpSpreadsheet**, 50 rows per sheet | Required by the brief; sheet chunking bounds each sheet. |
| Row source | reuse consolidated read model via a **streaming** port (`->cursor()`), no `LIMIT` | One source of truth for the listing; stream avoids loading all rows / deep OFFSET. |
| Storage | Laravel **`local`** filesystem disk (`reports/{id}.xlsx`) | Simple; download streams from disk. Object storage + signed URLs → #7. |
| Email | **notification with a download link** (Mailable via Mailpit) | Links don't hit SMTP size limits; attachments don't scale. |
| Status model | a **stateful `Report` aggregate** with guarded transitions | Observable lifecycle; failures are recorded, not lost. |
| Download when not ready | **`409 Conflict`** | The resource exists but isn't in a downloadable state yet. |
| Unknown report id | **`404 Not Found`** | No report with that id. |
| Invalid sort/direction/filter | **`422`** (same whitelist as the listing) | Fail loud; reuse the listing's validation. |

## 5. External contract (source for `docs/openapi.yaml`)

### `POST /candidatures/consolidated/export`
Body/query: same as the listing — `sort`, `direction` (`asc`|`desc`), `filter[<col>]=<value>`.
- **`202 Accepted`**: `{ "data": { "id": "<ulid>", "status": "pending" } }`
- **`422`**: invalid `sort`/`direction`/filter key.

### `GET /reports/{id}`
- **`200 OK`**:
```json
{
  "data": {
    "id": "01J...",
    "type": "consolidated_listing",
    "status": "completed",
    "requested_at": "2026-07-04T10:00:00+00:00",
    "completed_at": "2026-07-04T10:00:07+00:00",
    "download_url": "http://localhost:8080/reports/01J.../download",
    "failure_reason": null
  }
}
```
- **`404`**: unknown id.

### `GET /reports/{id}/download`
- **`200 OK`**: `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`,
  the `.xlsx` body.
- **`409`**: the report exists but is not `completed`.
- **`404`**: unknown id.

## 6. Testing approach (real mysql-test; external boundaries faked only)
- **Domain unit** — the lifecycle: `request()` yields `PENDING`; legal transitions work; an illegal
  transition (e.g. `markCompleted` on a `PENDING` report) throws `InvalidReportTransition`.
- **Feature/integration** (`sync` queue driver, real mysql-test):
  - `POST /export` → `202` with a report id and `pending`.
  - end to end with the sync driver: after the request, `GET /reports/{id}` reports `completed` with a
    `download_url`; `GET .../download` returns the `.xlsx` (correct content type).
  - seed > 50 assigned candidatures → the downloaded workbook has sheets of at most 50 rows (read back
    with PhpSpreadsheet).
  - an export with a filter produces a workbook containing only the matching candidatures.
  - `GET /reports/{id}` for an unknown id → `404`; `GET .../download` before completion → `409`.
  - an invalid `sort` on `POST /export` → `422`.
  - completion sends the email (`Mail::fake()` — SMTP is an external boundary).
  - a failing writer (bound to a fake that throws) drives the report to `failed` with a reason.
- **Boundaries faked, internals real**: `Mail` (SMTP) and `Storage` (filesystem) are external and may
  be faked; the queue runs on the `sync` driver so the real job/use case/reader run. No internal mocks.

## 7. Out of scope / deferred → `docs/scalability-backlog.md`
- Streaming spreadsheet writer (constant memory) / unbuffered PDO cursor for very large exports.
- Object storage (S3) + time-limited **signed** download URLs (instead of a plain local path).
- **Idempotency** of re-requests (same filters within a window reuse a report) and de-duplication.
- Notification on **failure** (not just completion) and retry/backoff tuning; dead-letter handling.
- Download **authorization** (who may fetch a report) — no auth in this exercise.

## 8. Open questions
- None open.
