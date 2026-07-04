# Performance notes (capability #7 — scalability)

A running log of the **measurements and the reasoning** behind each scalability slice, so the design
decisions can be explained (and re-checked) later. Rule we follow: *measure first, then fix, then
measure again* — numbers, not guesses.

## Methodology

- **Volume seeded in dev** (MySQL 8.4): ~10,025 candidatures, ~8,015 assignments, ~205 evaluators
  (≈40 candidates per evaluator, so `GROUP_CONCAT`/`COUNT` and deep pages are meaningful).
- **Tool:** `EXPLAIN ANALYZE` (actual execution time + plan) on the exact query the reader builds.
- Times are indicative (single machine, warm buffer pool); the goal is relative before/after and the
  shape of the plan, not absolute SLAs.

---

## Slice 1 — Response caching (Redis)

### Before (no cache) — the problem, measured

| Query | Actual time | What dominates |
|---|---|---|
| Consolidated listing, **page 1** (default sort) | **~39 ms** | ~38 ms is the `Materialize` of the derived table: the `GROUP_CONCAT`+`COUNT` **scans all 8,015 assignments and aggregates them in full**, just to return 15 rows — and it re-runs on **every** request. The main path (reverse index scan on `years_of_experience` + `LIMIT`) is cheap (~19 rows read). |
| Consolidated listing, **deep page** (`OFFSET 7000`) | **~118 ms** | `LIMIT/Offset 15/7000` walks 7,015 rows through the join and **discards** them. Grows linearly with page depth. → motivates **keyset pagination** (slice 4). |
| Consolidated listing, **sort by aggregate** (`evaluator_total`) | **~40 ms** | `Sort: stats.total DESC` → **filesort** on the materialized derived table (no index possible). |

**Reading of the numbers:** the ~38 ms per request is a *fixed* cost paid on every call regardless of
page — the perfect target for caching (a Redis GET is sub-millisecond). Deep-`OFFSET` cost grows with
depth (keyset territory). Aggregate-sort filesort is documented (summary table = out of scope here).

### Design decisions (and why)

- **Where:** a **decorator** over the read ports (`CachingConsolidatedListingReader`,
  `CachingCandidatureValidationReader`) implementing the same interface and wrapping the real reader
  with `Cache::remember`. The existing readers are untouched; only the DI binding changes. Clean
  cross-cutting concern via the decorator pattern.
- **Validation cache:** candidatures are **immutable** (insert-only; states deferred), so
  `GET /candidatures/{id}/validation` depends only on immutable data → cache with a **long TTL
  (~24h), no invalidation**. (If candidature mutability is ever added, this key must be invalidated.)
- **Listing cache — invalidation via version-key (namespace):** the listing is a view over the
  **growing `assignments` collection**, so a cached page goes stale when an assignment is created.
  There are many keys (one per filter+sort+page), so instead of enumerating them:
  - keep a permanent counter `consolidated-listing:version` in Redis;
  - embed it in every key: `consolidated-listing:v{N}:{hash(params)}`;
  - on any assignment write, `INCR` the version → all `v{N}` keys are orphaned at once (O(1)
    invalidation, no key scan) and expire on their own via a safety TTL.
- **Trigger:** a **decorator over `AssignmentRepository`** that `INCR`s the version after `save()`, so
  the use cases (EvaluatorAssigner, auto-assign) stay untouched.
- **TTL of listing entries: ~600 s (10 min), configurable.** With version-key invalidation the TTL is
  **not** what keeps data correct (the `INCR` does) — it only (a) reclaims memory of orphaned keys and
  (b) is a fallback max-staleness if an `INCR` is ever missed. 10 min balances good hit rates for
  bursty identical reads vs quick self-cleanup. Memory is negligible (~few KB/page × hundreds of keys
  = a few MB).
- **The version counter has NO TTL** (permanent, source of truth). If it expired and was recreated at
  a lower value, we could point back at a stale `v{N}` key that still exists → wrong data.
- **TTLs live in config**, not hardcoded, so they are tunable without code changes.

### After (with cache) — measured (reader-level, Redis, same volume)

Timed `ConsolidatedListingReader::read()` directly (isolates query vs cache; the full HTTP request is
dominated by ~15-20 ms of per-request framework boot, which hides the win end-to-end):

| Scenario | Time | Notes |
|---|---|---|
| Listing — cache **miss** (first call) | **~116 ms** | runs the query, hydrates rows, serializes + writes to Redis (cold) |
| Listing — cache **hit** (repeat) | **~0.1–0.2 ms** | Redis GET + array→DTO decode |
| **Speedup on hit** | **~588×** | the ~38 ms aggregate materialisation is skipped entirely |
| After a new assignment (version `INCR`) | next call is a miss again | verified by the invalidation test — the listing reflects the new assignment |

**Bug caught by measuring (important):** the first Redis cache **hit returned HTTP 500** —
`__PHP_Incomplete_Class`. On a pure hit, the DTO classes weren't autoloaded yet when the store called
`unserialize()`. The `array` cache store used in tests does **not** serialize, so the feature tests
were green and masked it. Fix: the cache adapters store a **plain-array form** of the result (no class
dependency at unserialize) and rebuild the DTOs on read. Lesson: cache plain data, not behaviour
objects — and test the caching path on a store that actually serializes (or assert against the real
Redis store).

---

## Slice 2 — Request idempotency (write endpoints)

_Design + measurements TBD._

## Slice 3 — Concurrency lock (bulk auto-assign)

_Design + measurements TBD._

## Slice 4 — Keyset pagination (consolidated listing)

_Baseline already captured above: deep `OFFSET 7000` ≈ 118 ms. Keyset target: constant time at any
depth. Design + after-measurements TBD._
