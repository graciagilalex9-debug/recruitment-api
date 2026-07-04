# Design — add-request-idempotency

Make `POST /candidatures/consolidated/export` safe to retry via an `Idempotency-Key` header: the first
request is processed and its response stored; retries replay it. Implemented as a reusable middleware
so any future write endpoint can opt in.

## 1. Why only the export (and not POST /candidatures)

Idempotency can come from a **natural key** or an **explicit key**:
- `POST /candidatures` has a **unique email** → a retry never creates a duplicate (it 409s). Already
  idempotent in state; no explicit key needed.
- `POST /export` has **no natural key** → a retry creates a new report + a new heavy job. This is where
  an explicit `Idempotency-Key` is needed.

So the mechanism is built generically but applied only to the export for now.

## 2. Architecture — a reusable middleware

```
POST /candidatures/consolidated/export   (route ->middleware('idempotent'))
  → EnsureIdempotency (App\Shared\Infrastructure\Http)
       ├─ no Idempotency-Key header      → pass through (behaviour unchanged)
       └─ with key:
            1. stored result for key?
                 - body fingerprint matches → REPLAY stored {status, body} (+ Idempotency-Replayed: true)
                 - fingerprint differs      → 422 (key reused with a different payload)
            2. else acquire lock idempotency:lock:{key}
                 - not acquired (in flight) → 409 (request in progress)
                 - acquired: re-check (1) [it may have completed], else run $next,
                   store {status, body, fingerprint} (TTL), release lock, return response
```

Placed in a new `App\Shared\Infrastructure\Http` namespace (cross-cutting infra, reusable across
contexts), registered with the alias `idempotent` in `bootstrap/app.php` and attached to the export
route. The controller is untouched.

## 3. Keys, storage and fingerprint (Redis)

| Item | Value |
|---|---|
| Result key | `idempotency:result:{idempotency-key}` → `{status, body, fingerprint}` |
| Lock key | `idempotency:lock:{idempotency-key}` (via `Cache::lock`) |
| Fingerprint | hash of `METHOD + path + raw request body` — detects "same key, different payload" |
| TTL | `config('performance.idempotency_ttl')`, default 86400 s (24 h) — retries happen in minutes |

The `Idempotency-Key` is client-generated (a UUID). We do not restrict its format beyond non-empty.

## 4. Decisions (and why)

| Concern | Pick | Why |
|---|---|---|
| Scope | export only | it lacks a natural key; `/candidatures` already idempotent via unique email |
| Header | **optional** `Idempotency-Key` | opt-in; without it, nothing changes (no broken clients/tests) |
| Concurrent same key | **lock → `409`** | prevents double-processing before the first finishes; simplest safe option |
| Same key, different body | **`422`** | a client bug; fail loud rather than silently replay the wrong response |
| Replay marker | header `Idempotency-Replayed: true` | lets clients/telemetry see a replay; body/status identical |
| Store | Redis (already the cache/lock backend) | fast, TTL'd, shared across app instances |
| What is stored | response **status + body** | enough to replay the exact outcome (the export's `202` + report id) |

For the export, replaying means a retry returns the **same `202` and the same report id** — so it polls
the original report; no second report or job is created.

## 5. Testing approach (real mysql-test; `array` cache store)
- **Replay:** `POST /export` with an `Idempotency-Key` → `202` + report id; the same key + same body
  again → `202` with the **same** report id, and only **one** report exists in the DB.
- **Different body, same key → `422`.**
- **In-progress → `409`:** pre-acquire the lock for a key, then a request with that key returns `409`.
- **No key → unchanged:** two requests without a key create two reports.
- `array` cache store supports `lock`/`add`/`get`/`put`, so the middleware is exercised for real; no
  internal mocks.

## 6. Out of scope / deferred → `docs/scalability-backlog.md`
- Idempotency for **bulk auto-assign** — its concurrency is handled by a lock (slice 3); a full
  idempotent-bulk contract is a larger design.
- Persisting idempotency records in a **durable store** (DB) if they must survive a Redis flush.
- A configurable **allow-list** of endpoints / required-key mode per route.

## 7. Open questions
- None open.
