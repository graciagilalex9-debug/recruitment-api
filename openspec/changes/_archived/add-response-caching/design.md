# Design — add-response-caching

Cache the two hot reads (consolidated listing, validation report) in Redis, keeping them correct.
Measurements that motivate this are in `docs/performance-notes.md` (§ Slice 1) — the listing pays a
fixed ~38 ms/request for the aggregate derived table. Caching is added without touching the existing
readers, via the **decorator pattern** over the read ports.

## 1. Architecture — caching decorators over the ports

```
GET /candidatures/consolidated
  Controller → ConsolidatedListingReader (port)
                 ├── CachingConsolidatedListingReader   ← Cache::remember (Redis), version-keyed
                 └──── QueryBuilderConsolidatedListingReader  (the real query, on miss)

GET /candidatures/{id}/validation
  Controller → CandidatureValidationReader (NEW port)
                 ├── CachingCandidatureValidationReader ← Cache::remember (long TTL)
                 └──── CandidatureValidationFinder        (the real rule computation, on miss)

PUT /candidatures/{id}/evaluator, POST /candidatures/auto-assign
  use case → AssignmentRepository (port)
                 ├── CachingAssignmentRepository         ← after save(): bump listing version
                 └──── EloquentAssignmentRepository
```

Only the DI bindings change; the concrete readers/repository are untouched. The validation path today
depends on the concrete `CandidatureValidationFinder`, so this change **extracts a
`CandidatureValidationReader` port** (the finder implements it) to make it decoratable.

## 2. Listing cache — version-key invalidation

The listing has many cache entries (one per filter+sort+page), so instead of enumerating keys on a
write we namespace them by a version counter:

- Permanent counter in Redis: `consolidated-listing:version` (created at 1, **no TTL**).
- Every entry key embeds it: `consolidated-listing:v{N}:{md5(serialized query params)}`.
- **Read:** `version = cache.get(version_key, 1)`; `cache.remember("…:v{version}:{hash}", ttl, fn)`.
- **Invalidate (on any assignment save):** `cache.increment(version_key)`. All `v{N}` entries are
  orphaned at once — O(1), no key scan — and expire via their safety TTL.

Why version-key over alternatives (see performance-notes for the full comparison): O(1) invalidation
without enumerating keys, plain-Redis (no tag bookkeeping), transparent, reflects writes immediately.

## 3. TTLs (config, not hardcoded — `config/performance.php`)

| Entry | TTL | Rationale |
|---|---|---|
| Listing page (`consolidated-listing:v{N}:…`) | **600 s** (10 min) | Correctness comes from the version `INCR`, not the TTL. The TTL only reclaims orphaned keys and bounds staleness if an `INCR` is ever missed. 10 min = good hit rate for bursty reads + quick self-cleanup. |
| Validation report (`candidature-validation:{id}`) | **86400 s** (24 h) | Candidature is immutable → the report never changes; long TTL, no invalidation. |
| `consolidated-listing:version` counter | **none (permanent)** | Source of truth. If it expired and reset lower, we could read a stale `v{N}` entry that still exists. |

## 4. Correctness argument

- **Validation:** cached value depends only on immutable candidature fields → can never go stale
  within the app's current rules. (Documented caveat: if candidature **states/mutability** are added
  later, this key must be invalidated on change.)
- **Listing:** the only inputs that change the result are **assignment** rows (candidatures are
  immutable and unassigned ones don't appear). Every assignment write goes through
  `AssignmentRepository.save()`, which the decorator instruments to bump the version → the next listing
  read is a miss and recomputes. So a listing request always reflects assignments created before it.

## 5. Testing approach (real mysql-test; `array` cache store in tests for isolation)
- **Freshness / invalidation (the key test):** request the listing (warm the cache) → create an
  assignment through the repository → request again → the new assignment is reflected (updated total /
  new row). Proves the version-key invalidation end to end.
- **Cache hit avoids the DB:** warm the listing, then with the query log enabled a second identical
  request runs **zero** DB queries. Same for the validation report.
- **Correctness preserved:** cached responses match the uncached contract (same rows/report).
- `phpunit.xml` sets `CACHE_STORE=array` so the cache is available and isolated per test (ArrayStore
  supports `add`/`increment`/`remember`). No internal mocks — real readers, real repository, real
  cache store.

## 6. Out of scope / deferred → `docs/scalability-backlog.md`
- Per-evaluator **summary table / materialized aggregates** (removes the derived table entirely).
- **Cache stampede** protection (lock/`Cache::lock` on miss so N concurrent misses don't all recompute).
- **Validation-cache invalidation** when candidature mutability (states) is introduced.
- Bumping the listing version **once per bulk auto-assign** instead of once per assignment (harmless
  now — the version just advances by N).

## 7. Open questions
- None open.
