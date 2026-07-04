# Change: add-consolidated-listing

## Why
There is no way to see the assigned candidatures together with their evaluator and the workload
context an evaluator carries. Reviewers need one consolidated, sortable, filterable, paginated view of
who is evaluating whom.

- No endpoint returns candidatures joined with their evaluator and assignment date.
- Per-evaluator context (how many candidatures they handle, which candidates) is not exposed anywhere.
- There is no way to sort, filter or page through the assignments at scale.

## What changes
- A new `GET /candidatures/consolidated` endpoint returns every candidature that has an evaluator
  assigned, each row showing: candidate full name, email, years of experience, evaluator name,
  assignment date, the **total candidatures assigned to that evaluator**, and a **concatenated list of
  the emails** of all candidates that evaluator handles.
- Results can be **ordered** by any listed column and direction (default: years of experience,
  descending).
- Results can be **filtered** by any listed column (exact match; prefix match for text columns).
- Results are **paginated**.
- Designed to use indexes and avoid full table scans (see the design and `docs/scalability-backlog.md`).

## Impact
### Capabilities (specs)
- **NEW** `consolidated-listing` — the joined, aggregated, sortable/filterable/paginated view.

### External contracts
- **NEW route:** `GET /candidatures/consolidated` — query params for sort, filter, pagination.

### Affected areas in this repo (coarse)
- **Code:** a read-only slice — a Query Builder reader returning row DTOs, a controller + resource +
  route. Pure CQRS read; no aggregates, no writes, no changes to existing contexts.
- **Database:** one new index (`candidatures.years_of_experience`, the default sort); no schema/table
  changes.
- **External contract:** one new route.

### Risk / migration
- Low: read-only, additive, one index. High-load hardening (caching, keyset pagination, GROUP_CONCAT
  limits, materialized aggregates) is recorded in `docs/scalability-backlog.md` and deferred to the
  `scalability` capability (#7); this change already filters/sorts through indexes by design.
