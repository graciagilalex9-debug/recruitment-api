# Change: add-excel-report

## Why
Reviewers can browse the consolidated listing online, but there is no way to take it away as a file
for offline analysis, sharing or archiving. A large listing is also too slow to render synchronously,
so producing it must not block the HTTP request.

- No endpoint exports the consolidated listing to a downloadable file.
- A big listing (thousands of rows) cannot be generated inside a request without timing out and tying
  up a web worker.
- There is no background-processing path in the app yet (no queue, no worker-driven work, no
  completion notification).

## What changes
- A new `POST /candidatures/consolidated/export` accepts the **same filter/sort** params as the
  listing and responds `202 Accepted` with a **report id** in `pending` status — it does not build the
  file inline.
- The file is built **asynchronously** by a queued job (Redis queue + worker): the report moves
  `pending → processing → completed` (or `failed`), and the workbook splits candidatures into sheets
  of **at most 50 rows** each.
- `GET /reports/{id}` returns the report status (and a download link once `completed`).
- `GET /reports/{id}/download` streams the generated `.xlsx` when the report is `completed`.
- When generation completes, the requester is **notified by email** (Mailpit in dev) with the
  download link.

## Impact
### Capabilities (specs)
- **NEW** `excel-report` — asynchronous Excel export of the consolidated listing with status tracking,
  download and email notification.

### External contracts
- **NEW route:** `POST /candidatures/consolidated/export` — `202` + report id.
- **NEW route:** `GET /reports/{id}` — report status.
- **NEW route:** `GET /reports/{id}/download` — the `.xlsx` file.

### Affected areas in this repo (coarse)
- **Code:** a new `Report` bounded context (aggregate with a status lifecycle, repository, use cases,
  HTTP). A queued Job + an email notification (first async path in the app). Reuses the
  `consolidated-listing` read model via a new **streaming** read port in the Assignment context.
- **Database:** one new table `reports`.
- **External contract:** three new routes; a new external dependency **PhpSpreadsheet**.

### Risk / migration
- Additive: new table, new routes, new dependency; no change to existing behaviour.
- The report lifecycle is the app's first use of a queue/worker and its first stateful aggregate.
  High-volume hardening (streaming spreadsheet writer, unbuffered cursor, object storage + signed
  URLs, idempotent re-requests, notification on failure) is recorded in `docs/scalability-backlog.md`
  and deferred to the `scalability` capability (#7).
