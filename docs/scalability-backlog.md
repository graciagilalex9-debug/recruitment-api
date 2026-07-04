# Scalability backlog (→ capability #7 `scalability`)

A running list of everything we consciously deferred to the future `scalability` capability, so when
we get there nothing is forgotten. PDF #7 asks for: **cache, queues, idempotency, concurrency** and
"high performance / horizontal scalability". Each item below says **what**, **why we deferred it**,
**where it came from**, and the **likely approach**.

> Rule of thumb we've followed: build it **correct and simple first**; make it **fast/robust under
> load** here in #7 — with measurements, not guesses.

---

## 1. Caching (read-heavy endpoints)

### 1.1 Cache the candidature validation report — DONE (baseline, #7 slice 1)
- **Status:** implemented as a caching decorator over the validation read port
  (`CachingCandidatureValidationReader`), `candidature-validation:{id}` in Redis, long TTL
  (`config/performance.php`), no invalidation — safe because candidatures are immutable.
- ⚠️ If candidature **states** (mutability) are ever added, this key must be invalidated on change.

### 1.2 Cache the consolidated listing — DONE (baseline, #7 slice 1)
- **Status:** implemented as a caching decorator over `ConsolidatedListingReader`, keyed per
  (filter+sort+page) and namespaced by a **version-key** (`consolidated-listing:v{N}:…`) that a
  decorator over `AssignmentRepository` bumps (`INCR`) on every assignment write → O(1) invalidation.
  Safety TTL from config. Measured ~588× faster on a hit (see `docs/performance-notes.md`).
- **What remains at real scale → §6 below.**

## 2. Queues (offload slow work)

### 2.1 Excel report generation + email notification — DONE (baseline, capability #6)
- **What:** generating the Excel of the consolidated listing (50 rows/sheet) and emailing when done.
- **Status:** implemented in #6 as `POST /candidatures/consolidated/export` → `202` + a `Report`
  aggregate (pending→processing→completed/failed), a queued `GenerateReportJob` on Redis, a
  PhpSpreadsheet writer (50/sheet) storing on the `local` disk, an email with a download link
  (Mailpit), and `GET /reports/{id}` + `GET /reports/{id}/download`.
- **What remains at scale → see §5 below.**

### 2.2 Bulk auto-assignment for very large backlogs
- **What:** `POST /candidatures/auto-assign` currently loads **all** unassigned candidatures and
  assigns them **synchronously** in one request, wrapped in a single DB transaction (atomic).
- **Why deferred:** correct and fine for normal sizes; a huge backlog would be slow / memory-heavy in
  a single request, and a single transaction over thousands of writes would hold locks too long.
- **From:** auto-assignment capability.
- **Approach:** chunk the backlog and dispatch a queued job (or several); return `202 Accepted` with a
  job/status reference; commit **per chunk** (batched transactions) instead of one big transaction, so
  atomicity is per-chunk and locks stay short.

## 3. Concurrency & idempotency

### 3.1 Concurrent bulk auto-assign runs — DONE (baseline, #7 slice 3)
- **Status:** the whole bulk operation runs under an exclusive lock. `CandidatureAutoAssigner` wraps
  its work in a `Mutex` port (`App\Assignment\Application\Lock\Mutex`), implemented by `LaravelMutex`
  (`Cache::lock('auto-assign', ttl)`, non-blocking, released in a `finally`, TTL in
  `config/performance.php`). A concurrent request fails fast with `AutoAssignInProgress` → `409`.
- **Remaining:** making the bulk **asynchronous** (queue it, return `202`) for very large backlogs — §2.2.

### 3.2 Single-assignment race safety — ALREADY DONE (baseline)
- **What:** two requests assigning the same candidature concurrently.
- **Status:** ✅ handled by the **`assignments.candidature_id` UNIQUE index** + upsert. This is the
  pattern to generalize.
- **From:** capability #3 (evaluator-assignment).

### 3.3 Idempotency keys for write endpoints — DONE (baseline, #7 slice 2)
- **Status:** a reusable `EnsureIdempotency` middleware (`App\Shared\Infrastructure\Http`) accepts an
  optional `Idempotency-Key` header, stores the response in Redis (`{status, body, fingerprint}`, TTL
  from `config/performance.php`) and replays it on retry; a lock guards concurrent same-key requests
  (`409`), and a reused key with a different body is rejected (`422`). Applied to
  `POST /candidatures/consolidated/export` (the write endpoint without a natural key).
- **Note:** `POST /candidatures` is idempotent by its **unique email** (a retry never duplicates), so
  it needs no explicit key. Extending the middleware to other write endpoints is one line each.

## 4. Query performance at scale (consolidated listing #4)

### 4.1 `GROUP_CONCAT` limit and cost
- **What:** the concatenated emails per evaluator.
- **Caveat:** `group_concat_max_len` (default 1024 bytes) **truncates** for evaluators with many
  candidates; the aggregation scans all their rows.
- **Approach:** raise `group_concat_max_len` for the session if needed; at real scale precompute into a
  summary table / cache (see 1.2), or return a **count + a separate paginated emails endpoint** rather
  than one giant string.

### 4.2 Sorting by an aggregate → filesort
- **What:** ordering by "total per evaluator" can't use an index.
- **Approach:** sort by indexed candidature columns where possible; precompute the aggregate into a
  column/summary table to make it sortable via index.

### 4.3 Deep pagination with `OFFSET` — DESIGNED, CONSCIOUSLY DEFERRED (#7 slice 4)
- **What:** `LIMIT x OFFSET y` scans and discards `y` rows → slow on deep pages (**measured ~118 ms at
  `OFFSET 7000`** vs ~39 ms on page 1).
- **Fix (designed):** an opt-in `?cursor=` keyset mode for the default order (years desc + a unique id
  tiebreaker) via `cursorPaginate()`, alongside the existing `?page=` offset; returns
  `next_cursor`/`prev_cursor`. See `docs/performance-notes.md` § Slice 4.
- **Decision:** deferred on purpose — realistic dataset sizes for this domain don't page thousands
  deep, so offset is adequate and keyset (which loses "jump to page N" / `total`) would be premature.
  Revisit when deep-page latency is actually observed. Arbitrary-column / aggregate-sort keyset is a
  larger follow-up.

### 4.4 Filtering — index-friendly by design (baseline)
- **Status:** ✅ we chose **exact + prefix (`value%`)** filters that use indexes, and avoided
  `%contains%` (which forces a full table scan).
- **From:** capability #4.
- **Approach for real text search:** a **full-text index** (MySQL `FULLTEXT`) or a search engine
  (Meilisearch/Elasticsearch) if "contains" search is ever required.

### 4.5 Indexes in place (baseline)
- `candidatures.email` (unique), `assignments.candidature_id` (unique), `assignments.evaluator_id`
  (index), and `candidatures.years_of_experience` (added in #4 for the default sort). Review with
  `EXPLAIN` under load in #7.

## 5. Excel export at scale (from capability #6)

### 5.1 Streaming spreadsheet writer / constant memory
- **What:** `PhpSpreadsheetConsolidatedReportWriter` builds the whole workbook in memory before saving.
- **Why deferred:** fine for the exercise; a very large export (100k+ rows) would exhaust memory.
- **Approach:** a streaming/box writer (e.g. `openspout/openspout`) that flushes rows to disk as it
  goes, keeping memory flat; or PhpSpreadsheet's cell-caching to a store.

### 5.2 Unbuffered DB cursor for the row stream
- **What:** `QueryBuilderConsolidatedListingStreamReader` uses `->cursor()`, which still relies on a
  buffered PDO query by default (mysqlnd buffers the whole result client-side).
- **Why deferred:** correct results now; memory grows with the result set.
- **Approach:** enable `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY = false` for the export connection so rows
  stream from the server, pairing with §5.1 for true constant-memory export. Also revisit keyset
  pagination (§4.3) to avoid the aggregate derived table cost on huge sets.

### 5.3 Object storage + signed download URLs
- **What:** files are stored on the `local` disk and served by a plain (unauthenticated) endpoint.
- **Why deferred:** no auth in this exercise; a single node is fine locally.
- **Approach:** store on S3-compatible object storage; hand out time-limited **signed URLs** instead of
  streaming through the app; add download **authorization** (only the requester / allowed roles).

### 5.4 Idempotency & de-duplication of export requests — DONE (baseline, #7 slice 2)
- **Status:** `POST /export` honours an `Idempotency-Key` (see §3.3): a retry with the same key+body
  replays the original `202` (same report id) instead of creating a new report + job.
- **Remaining:** automatic de-dup by a **hash of the criteria** (without a client-supplied key) so even
  keyless bursts of identical exports collapse — still deferred.

### 5.5 Notify on failure, not just completion
- **What:** an email is sent only when a report completes; a failed report is visible only via
  `GET /reports/{id}`.
- **Why deferred:** the failure is recorded (status `failed` + reason); proactive alerting is extra.
- **Approach:** also notify the requester on terminal failure; tune `--tries/--backoff`, add a
  dead-letter/failed-jobs review, and (with §5.3) capture the actual requester identity to address.

## 6. Caching at real scale (remaining, from #7 slice 1)

### 6.1 Per-evaluator summary table / materialized aggregates
- **What:** the `GROUP_CONCAT`/`COUNT` derived table still runs on every cache **miss** (~38 ms).
- **Approach:** maintain a per-evaluator summary row (total + emails) updated on assignment write, so
  even a miss is a cheap indexed read; removes the filesort on aggregate sort (§4.2) too.

### 6.2 Cache stampede protection on miss
- **What:** when a hot key expires (or the version bumps), N concurrent requests all miss and recompute
  the same query at once.
- **Approach:** a short lock around the recompute (`Cache::lock`) or `Cache::remember`-with-lock so only
  one request rebuilds while others wait/serve stale. Ties into slice 3 (locks).

### 6.3 Validation-cache invalidation if candidatures become mutable
- **What:** the validation cache has no invalidation (relies on immutability).
- **Approach:** if candidature **states** are ever added, invalidate `candidature-validation:{id}` on
  change (or version-key it like the listing).

---

## How we'll approach #7 (when we get there)
1. **Measure first** — `EXPLAIN` the listing, load-test with seeded volume, find the real bottleneck.
2. **Cache** the hot reads (validation, listing) with a clear invalidation story.
3. **Queue** the slow writes (Excel, large bulk auto-assign).
4. **Lock / idempotency** for concurrent bulk operations.
5. **Keyset pagination** + index review for the listing.
