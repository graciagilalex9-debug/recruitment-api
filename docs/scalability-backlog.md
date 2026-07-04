# Scalability backlog (→ capability #7 `scalability`)

A running list of everything we consciously deferred to the future `scalability` capability, so when
we get there nothing is forgotten. PDF #7 asks for: **cache, queues, idempotency, concurrency** and
"high performance / horizontal scalability". Each item below says **what**, **why we deferred it**,
**where it came from**, and the **likely approach**.

> Rule of thumb we've followed: build it **correct and simple first**; make it **fast/robust under
> load** here in #7 — with measurements, not guesses.

---

## 1. Caching (read-heavy endpoints)

### 1.1 Cache the candidature validation report
- **What:** `GET /candidatures/{id}/validation` recomputes the rules on every call.
- **Why deferred:** correctness first; caching adds invalidation concerns.
- **From:** capability #2 (candidature-validation).
- **Approach:** `Cache::remember("candidature-validation:{id}", ttl, …)` in Redis. Safe **because
  candidatures are immutable** (insert-only) → the report is stable. ⚠️ If we ever add candidature
  **states** (mutability), we must invalidate this key on change.

### 1.2 Cache the consolidated listing
- **What:** `GET /candidatures/consolidated` runs joins + a `GROUP_CONCAT`/`COUNT` derived table on
  every request.
- **Why deferred:** the query is correct but heavy; caching needs an invalidation strategy.
- **From:** capability #4 (consolidated-listing).
- **Approach:** cache per (filter+sort+page) key in Redis with a short TTL, or invalidate on any new
  assignment/candidature. Consider a **materialized view / summary table** refreshed on write for the
  per-evaluator aggregates.

## 2. Queues (offload slow work)

### 2.1 Excel report generation + email notification
- **What:** generating the Excel of the consolidated listing (50 rows/sheet) and emailing when done.
- **Why deferred:** it's capability #6, but it's inherently a background job.
- **From:** PDF #6.
- **Approach:** a queued `Job` on Redis (the `worker` container already runs `queue:work`); on
  completion send mail (Mailpit in dev). Store/serve the generated file.

### 2.2 Bulk auto-assignment for very large backlogs
- **What:** `POST /candidatures/auto-assign` currently loads **all** unassigned candidatures and
  assigns them **synchronously** in one request.
- **Why deferred:** correct and fine for normal sizes; a huge backlog would be slow / memory-heavy in
  a single request.
- **From:** auto-assignment capability.
- **Approach:** chunk the backlog and dispatch a queued job (or several); return `202 Accepted` with a
  job/status reference instead of a synchronous summary.

## 3. Concurrency & idempotency

### 3.1 Concurrent bulk auto-assign runs
- **What:** two simultaneous `auto-assign` calls could double-process / unbalance.
- **Why deferred:** single assignments are already race-safe (see 3.2); concurrent *bulk* runs are the
  gap.
- **From:** auto-assignment capability.
- **Approach:** a lock (`Cache::lock('auto-assign')`) so only one bulk run executes at a time, or make
  the job idempotent and queued (serialized on one queue).

### 3.2 Single-assignment race safety — ALREADY DONE (baseline)
- **What:** two requests assigning the same candidature concurrently.
- **Status:** ✅ handled by the **`assignments.candidature_id` UNIQUE index** + upsert. This is the
  pattern to generalize.
- **From:** capability #3 (evaluator-assignment).

### 3.3 Idempotency keys for write endpoints
- **What:** a client retrying a POST shouldn't create duplicates.
- **Why deferred:** email-uniqueness already prevents duplicate candidatures (a natural idempotency
  key); a general mechanism wasn't needed yet.
- **From:** general PDF #7.
- **Approach:** accept an `Idempotency-Key` header, store processed keys (Redis) with the response,
  replay on repeat.

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

### 4.3 Deep pagination with `OFFSET`
- **What:** `LIMIT x OFFSET y` scans and discards `y` rows → slow on deep pages.
- **Approach:** **keyset / cursor pagination** (`WHERE (sort_col, id) < (last_seen…)`), which stays
  fast at any depth.

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

---

## How we'll approach #7 (when we get there)
1. **Measure first** — `EXPLAIN` the listing, load-test with seeded volume, find the real bottleneck.
2. **Cache** the hot reads (validation, listing) with a clear invalidation story.
3. **Queue** the slow writes (Excel, large bulk auto-assign).
4. **Lock / idempotency** for concurrent bulk operations.
5. **Keyset pagination** + index review for the listing.
