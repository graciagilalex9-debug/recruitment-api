# Change: add-request-idempotency

## Why
`POST /candidatures/consolidated/export` has no natural key, so a client that retries after a lost
response (timeout, flaky network, double-click) creates a **second report and a second heavy job** —
duplicated work and confusing results. Unlike `POST /candidatures` (already idempotent via its unique
email), the export needs an explicit idempotency mechanism.

- A retried export request creates a duplicate report + queued job.
- There is no way for a client to safely retry a create without risking duplicates.
- The app has no general idempotency mechanism for write endpoints that lack a natural key.

## What changes
- Clients MAY send an `Idempotency-Key` header on `POST /candidatures/consolidated/export`. The first
  request with a given key is processed normally and its response is stored; any retry with the same
  key **replays the stored response** instead of creating a new report.
- Reusing a key with a **different** request body is rejected (`422`) — a client bug guard.
- A second request with the same key **while the first is still in progress** is rejected (`409`).
- Requests without the header behave exactly as today (opt-in, nothing breaks).
- `POST /candidatures` is intentionally left unchanged: its unique-email constraint already makes it
  idempotent (a retry never creates a duplicate).

## Impact
### Capabilities (specs)
- **NEW** `request-idempotency` — safe retries for the export via an `Idempotency-Key`.

### External contracts
- **Additive:** a new optional request header on the export endpoint; new `409`/`422` responses for
  key misuse. The success contract is unchanged.

### Affected areas in this repo (coarse)
- **Code:** a reusable idempotency **middleware** (stores/replays responses in Redis, with a lock for
  concurrent same-key requests), applied to the export route; a TTL in `config/performance.php`.
- **Database:** none (state lives in Redis).
- **External contract:** one optional header + two new error responses on one route.

### Risk / migration
- Opt-in and additive: without the header, behaviour is unchanged.
- Bounded by a TTL (retries happen within minutes; keys expire in 24h).
- Deferred/documented: idempotency for bulk auto-assign (covered by the concurrency lock, slice 3);
  extending the middleware to other future write endpoints as needed.
