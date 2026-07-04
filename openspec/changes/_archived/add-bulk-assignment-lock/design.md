# Design — add-bulk-assignment-lock

Serialize `POST /candidatures/auto-assign` so only one bulk run executes at a time, via a distributed
lock. Same locking primitive as the idempotency middleware (Redis `Cache::lock`), but here it guards a
whole **business operation** rather than a single request key.

## 1. The race (why single-assignment safety isn't enough)

`CandidatureAutoAssigner::assignAll()` does: read `unassignedCandidatures()` + `evaluatorLoads()`,
filter eligible, then distribute to the least-loaded evaluator keeping a live tally. Two concurrent
runs read the **same snapshot**:

```
Run A: unassigned [Ada, Bob, Cid], loads {E1:0, E2:0}
Run B: unassigned [Ada, Bob, Cid], loads {E1:0, E2:0}   ← same snapshot
A assigns Ada→E1, Bob→E2, Cid→E1        B assigns Ada→E2, Bob→E1, Cid→E2 (upsert overwrites)
```

The `assignments.candidature_id` unique index keeps each candidature to one row, but the **balancing**
is computed on stale data and both runs count the same candidatures → unbalanced + double work +
inflated counts. The fix is to make the read-then-distribute mutually exclusive.

## 2. Architecture — a Mutex port

```
POST /candidatures/auto-assign
  → CandidatureAutoAssigner::assignAll()
       └─ mutex.withLock('auto-assign', fn () => run())     [Mutex port, Application]
            ├─ lock free  → run the existing distribution, release on finish (finally)
            └─ lock held  → throw LockNotAcquired
                              → use case translates to AutoAssignInProgress (domain) → 409
```

- **`Mutex`** (port, `App\Assignment\Application\Lock`): `withLock(string $name, Closure $callback)` —
  run `$callback` while holding an exclusive lock; throws `LockNotAcquired` if the lock is already
  held. No TTL in the signature (an infrastructure detail).
- **`LaravelMutex`** (`App\Assignment\Infrastructure\Lock`): implements it with `Cache::lock($name,
  $ttl)->get()` (non-blocking → immediate `409`), releasing in a `finally`. TTL from config.
- **`CandidatureAutoAssigner`** wraps its current body (renamed to `run()`) in `withLock` and
  translates `LockNotAcquired` → `AutoAssignInProgress` (a domain exception like `NoEvaluatorsAvailable`,
  mapped to `409`). Application stays Laravel-free (it depends on the `Mutex` port; tests use a fake).

## 3. Decisions (and why)

| Concern | Pick | Why |
|---|---|---|
| Concurrent 2nd request | **`409` immediately** (non-blocking `get()`) | predictable; a bulk run can be long, so blocking would tie up a connection for a run that ends up a no-op |
| Granularity | **global** lock name `auto-assign` | the operation processes the *entire* backlog → only one makes sense at a time |
| Placement | **`Mutex` port + use case** | the use case owns its concurrency requirement; keeps Application framework-agnostic; reusable |
| Busy → error type | **`AutoAssignInProgress`** domain exception → `409` | consistent with the other 409 domain exceptions; clear API message |
| Lock TTL | **120 s** (config `auto_assign_lock_ttl`), released in `finally` | auto-releases if the process crashes mid-run (no deadlock); normal path releases immediately |
| Lock backend | Redis `Cache::lock` | already the cache/lock backend; works across app instances |

## 4. Testing approach (real mysql-test; `array` cache store)
- **Concurrent → 409:** pre-acquire the `auto-assign` cache lock, then `POST /candidatures/auto-assign`
  returns `409` (the run can't get the lock). Release.
- **Lock released after a run:** `POST /candidatures/auto-assign` succeeds (`200`), then a second call
  also succeeds (`200`) — the lock didn't stay held. (The second run simply finds less/no backlog.)
- **Alone behaviour unchanged:** an auto-assign with a backlog still assigns the eligible candidatures
  (existing auto-assignment tests keep passing).
- The `array` cache store implements atomic locks, so the mutex is exercised for real; the use-case
  unit tests use a trivial fake `Mutex` that just runs the callback.

## 5. Out of scope / deferred → `docs/scalability-backlog.md`
- Making the bulk **asynchronous** (dispatch a queued job, return `202` + status) for very large
  backlogs — §2.2.
- A fully **idempotent** bulk contract (safe replays of the same bulk run).
- Finer-grained locking (e.g. per-evaluator) — unnecessary while the operation is whole-backlog.

## 6. Open questions
- None open.
