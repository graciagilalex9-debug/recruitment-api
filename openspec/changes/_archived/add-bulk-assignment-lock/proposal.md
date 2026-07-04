# Change: add-bulk-assignment-lock

## Why
`POST /candidatures/auto-assign` reads the whole backlog of unassigned candidatures and the evaluator
loads, then distributes in one pass. That read-then-distribute is not atomic, so two concurrent runs
work from the **same snapshot** and collide.

- Two simultaneous auto-assign runs both see the same unassigned list and the same loads.
- They assign the same candidatures (upsert = last-writer-wins), producing an **unbalanced**
  distribution and **double work**.
- Both report inflated counts ("assigned N") for the same candidatures.
- Single assignments are already race-safe (the `assignments.candidature_id` unique index), but the
  **bulk operation as a whole** is not.

## What changes
- The bulk auto-assignment runs **one at a time**: it acquires an exclusive lock for the whole
  operation before reading/distributing, and releases it when done.
- A second auto-assign request that arrives while one is in progress gets **`409 Conflict`**
  ("an auto-assignment is already in progress") immediately — it does not wait or double-process.
- Behaviour when run alone is unchanged.

## Impact
### Capabilities (specs)
- **NEW** `bulk-assignment-lock` — serialized bulk auto-assignment (one at a time; concurrent → 409).

### External contracts
- **Additive:** a new `409` response on `POST /candidatures/auto-assign`. The success contract is
  unchanged.

### Affected areas in this repo (coarse)
- **Code:** a `Mutex` port (Application) + a `LaravelMutex` (Redis `Cache::lock`) implementation; the
  auto-assign use case wraps its work in the lock and maps a busy lock to a domain exception
  (`AutoAssignInProgress` → 409); a lock TTL in `config/performance.php`.
- **Database:** none (the lock lives in Redis).
- **External contract:** one new error response on one route.

### Risk / migration
- Additive; alone-run behaviour unchanged.
- The lock has a TTL so a crashed run auto-releases it (no deadlock); it is also released in a
  `finally` on normal completion.
- Deferred/documented: making the bulk **asynchronous** (queue it, return `202`) for very large
  backlogs; a fully idempotent bulk contract.
