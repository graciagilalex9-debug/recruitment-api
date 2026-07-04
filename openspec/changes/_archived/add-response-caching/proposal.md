# Change: add-response-caching

## Why
The read-heavy endpoints recompute expensive work on every request. Measured on ~10k candidatures /
8k assignments (see `docs/performance-notes.md`): the consolidated listing spends ~38 ms per call
materialising the `GROUP_CONCAT`/`COUNT` derived table over all assignments — a fixed cost paid on
every request, even for page 1. Under load this is the first bottleneck.

- `GET /candidatures/consolidated` re-runs the joins + aggregate derived table on every request.
- `GET /candidatures/{id}/validation` recomputes the eligibility rules on every request, though a
  candidature is immutable (its report never changes).
- There is no caching layer, so identical repeated reads pay full cost every time.

## What changes
- The consolidated listing is served from a **Redis cache** (per filter+sort+page), and the validation
  report is cached too. Endpoints and their JSON are unchanged — only faster.
- The listing cache stays **fresh**: creating an assignment invalidates it (via a version-key bumped
  on write), so a listing request always reflects assignments made before it.
- The validation cache uses a long TTL with no invalidation, safe because candidatures are immutable.
- Caching is added as **decorators** over the existing read ports — the current readers are untouched.

## Impact
### Capabilities (specs)
- **NEW** `response-caching` — cached-but-consistent reads for the consolidated listing and the
  validation report.

### External contracts
- None. Same routes, same request/response shapes (a pure performance change).

### Affected areas in this repo (coarse)
- **Code:** caching decorators over `ConsolidatedListingReader` and the validation reader; a
  version-key cache helper; a decorator over `AssignmentRepository` to invalidate on write; DI
  rebindings; a `config/performance.php` for TTLs.
- **Database:** none.
- **External contract:** none. Uses the existing Redis (cache store).

### Risk / migration
- Main risk of any cache is **staleness**; mitigated by version-key invalidation on assignment writes
  (listing) and by immutability (validation). A safety TTL bounds any missed invalidation.
- Deferred to later #7 slices / documented: per-evaluator summary table, cache stampede protection on
  miss, and validation-cache invalidation if candidature mutability (states) is ever added.
