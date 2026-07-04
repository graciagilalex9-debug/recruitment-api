# Change: add-auto-assignment

## Why
Assigning evaluators one by one is tedious when a batch of candidatures arrives. There is no way to
distribute the pending, eligible candidatures across evaluators in a single operation, balanced by
each evaluator's current workload.

- Every candidature must be assigned manually, one request at a time.
- Nothing balances the workload across evaluators.
- There is no single operation to clear the backlog of unassigned, eligible candidatures.

## What changes
- A new `POST /candidatures/auto-assign` endpoint assigns, in one call, every candidature that is
  **both unassigned and eligible** (passes its validation rules) to the **least-loaded** evaluator,
  rebalancing as it goes.
- Already-assigned candidatures are left untouched; ineligible ones are skipped.
- Responds `200 OK` with a summary: how many were assigned and how many were skipped as ineligible.
- Responds `409 Conflict` when there are candidatures to assign but no evaluators exist.

## Impact
### Capabilities (specs)
- **NEW** `auto-assignment` — bulk-assign unassigned, eligible candidatures to the least-loaded evaluator.

### External contracts
- **NEW route:** `POST /candidatures/auto-assign`

### Affected areas in this repo (coarse)
- **Code:** a new auto-assignment use case (Application) reusing the `Assignment` repository, the
  `CandidatureValidator` (eligibility) and a new read (unassigned candidatures + per-evaluator load);
  an HTTP controller + route. No change to the Candidature / Evaluator / Assignment aggregates.
- **Database:** none — a new read query only; reuses `candidatures` / `evaluators` / `assignments`.
- **External contract:** one new route.

### Risk / migration
- Low: additive; a read plus upserts on existing tables. Single-assignment races are already guarded
  by the unique assignment index. Heavy concurrency / queue hardening for very large batches is
  deferred to the `scalability` capability (#7).
