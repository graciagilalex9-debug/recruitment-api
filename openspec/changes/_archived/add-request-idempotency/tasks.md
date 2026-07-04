# Tasks — add-request-idempotency

Capability: **request-idempotency**. A reusable idempotency middleware applied to
`POST /candidatures/consolidated/export`, so retries with an `Idempotency-Key` replay the stored
response instead of creating a duplicate report. Every scenario is covered by a test in §3.

## 1. Middleware and config
- [x] 1.1 `config/performance.php` — add `idempotency_ttl` (default 86400), env-overridable.
- [x] 1.2 `App\Shared\Infrastructure\Http\EnsureIdempotency` middleware:
  - no `Idempotency-Key` header → `$next($request)` unchanged.
  - compute fingerprint = hash of method + path + raw body.
  - stored result exists → fingerprint matches: replay `{status, body}` (+ `Idempotency-Replayed: true`
    header); differs → `422`.
  - else `Cache::lock('idempotency:lock:{key}')`: not acquired → `409`; acquired → re-check stored,
    else run `$next`, store `{status, body, fingerprint}` with the TTL, release the lock, return.

## 2. Wiring
- [x] 2.1 Register the middleware alias `idempotent` in `bootstrap/app.php` (`withMiddleware`).
- [x] 2.2 Attach `->middleware('idempotent')` to the `POST /candidatures/consolidated/export` route.

## 3. Tests (real mysql-test; `array` cache store)
- [x] 3.1 Feature — same `Idempotency-Key` + same body twice → both `202` with the **same** report id,
  and exactly **one** report row exists. *(covers: a retry replays the response, no duplicate)*
- [x] 3.2 Feature — same key + a **different** body → `422`. *(covers: key reused with a different
  payload is rejected)*
- [x] 3.3 Feature — a request whose key lock is already held → `409`. *(covers: concurrent same-key
  request is rejected)*
- [x] 3.4 Feature — two requests **without** a key create two reports (behaviour unchanged).
  *(covers: the header is opt-in)*

## 4. Docs
- [x] 4.1 `docs/openapi.yaml` — document the optional `Idempotency-Key` header and the `409`/`422`
  responses on the export endpoint.
- [x] 4.2 `http/reports.http` — add a request that sends `Idempotency-Key` (and a repeat with the same
  key) to see the replay.
- [x] 4.3 `docs/scalability-backlog.md` — mark §3.3 / §5.4 (idempotency) as DONE (baseline) for the
  export; note the deferred bits.
- [x] 4.4 README — note idempotent retries under "Scalability".

## 5. Validation
- [x] 5.1 `make quality` (Pint + PHPStan L8 + tests) green.
