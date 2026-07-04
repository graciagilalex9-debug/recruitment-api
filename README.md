# Recruitment API

A REST API to manage job **candidatures** and **evaluators**, built with **Laravel 13 / PHP 8.4** on a
**Hexagonal + DDD** architecture. Everything runs in Docker — no local PHP required.

> Built as a Senior Backend (Laravel) exercise. The focus is architectural criterion: decoupling,
> clean design, patterns, testing and scalability — with Laravel treated as an infrastructure detail,
> not the core.

---

## Table of contents

- [Architecture](#architecture)
- [Project structure](#project-structure)
- [Key design decisions](#key-design-decisions)
- [Patterns used](#patterns-used)
- [Getting started](#getting-started)
- [Commands](#commands)
- [API](#api)
- [Testing](#testing)
- [Scalability](#scalability)
- [Roadmap](#roadmap)

---

## Architecture

**Guiding principle: Laravel is an infrastructure detail, not the core.** Dependencies point inward
only — the domain knows nothing about the framework.

```
┌──────────────────────────────────────────────────────────────┐
│  Infrastructure  (Laravel = detail)                           │
│  ├─ Http/         Controllers, FormRequests, Resources, Mware │
│  ├─ Persistence/  Eloquent repos + models + mappers; QB readers│
│  ├─ Cache/        caching decorators + version-key helper      │
│  ├─ Queue/        queued Jobs (async Excel report)            │
│  ├─ Mail/         Mailables + notifier                        │
│  ├─ Lock/         Redis mutex (distributed lock)             │
│  └─ Providers/    interface → implementation binding          │
├──────────────────────────────────────────────────────────────┤
│  Application  (use cases)                                      │
│  └─ one class per use case; ports (Reader, Dispatcher,        │
│     Writer, Notifier, Mutex …) it depends on                  │
├──────────────────────────────────────────────────────────────┤
│  Domain  (pure PHP, ZERO Laravel)                             │
│  ├─ Aggregates, Value Objects (+ enums: Report state)        │
│  ├─ Extensible rule-pipeline validation                      │
│  └─ Repository/Reader interfaces (ports), exceptions          │
└──────────────────────────────────────────────────────────────┘
        Domain  ←  Application  ←  Infrastructure
```

The **golden rule of decoupling**: the Eloquent model lives only in `Infrastructure/Persistence` and is
**not** the domain entity. A **mapper** translates between them, so the data layer can be replaced
without rewriting business logic. Purity is enforced by *imports* (the domain imports nothing from
`Illuminate\*`) and checked by PHPStan level 8.

### Lightweight CQRS

- **Writes** go through repositories implemented with **Eloquent** (behind a domain interface).
- **Reads / complex queries** (e.g. the consolidated listing) use the **Query Builder** directly,
  returning DTOs, without going through the domain.

## Project structure

The code is organised by **bounded context**, each a full vertical slice with the same three layers:

```
app/
├─ Candidature/    registration · extensible rule-pipeline validation · summary (Collections)
├─ Evaluator/      evaluators
├─ Assignment/     assign · auto-assign (least-loaded) · consolidated listing (Query Builder reads)
│                  · listing cache (decorators + version-key) · Mutex (bulk lock)
├─ Report/         async Excel export — aggregate with a STATE lifecycle · queued Job · Mailable
└─ Shared/         cross-cutting infrastructure (idempotency middleware)

# every context is split the same way:
<Context>/
├─ Domain/           pure PHP, no framework — aggregates, Value Objects, ports (interfaces), exceptions
├─ Application/       use cases (one class each) + the ports they depend on + DTOs
└─ Infrastructure/   Http · Persistence · Cache · Queue · Mail · Lock · Providers (adapters + DI)

docker/            Dockerfile (php-fpm 8.4) + nginx config
config/performance.php   cache/idempotency/lock TTLs (tunable, env-overridable)
docs/openapi.yaml  HTTP contract (source of truth, spec-first) — Swagger UI at /docs
docs/performance-notes.md · docs/scalability-backlog.md   measurements + scalability decisions
http/              PhpStorm HTTP Client requests (manual, hit the dev DB)
openspec/          Spec-Driven Development artifacts (specs/ + archived changes)
tests/             Unit (pure) + Feature (integration, real MySQL) + Support (fakes, object mothers)
```

## Key design decisions

| Decision | Why |
|---|---|
| **Hexagonal + DDD from day 1** | Framework-independent business logic; the exercise rewards decoupling. |
| **ULID identity, generated via the repository port** | Time-sortable ids owned by the domain type; generation is infrastructure, so the domain stays framework-free and testable without a DB. |
| **Email is the business identity (unique)** | Duplicate applications make no sense; uniqueness is a domain rule → `409 Conflict`. |
| **Two-layer uniqueness defense** | App-level `existsByEmail` (happy path) **+** DB unique index (race-safe guarantee). Under concurrency, only the constraint prevents a duplicate; the repo translates the violation into a domain exception. |
| **Value Objects enforce invariants** | An invalid `Email` / `YearsOfExperience` cannot be constructed — illegal states are unrepresentable. |
| **DTOs at the application boundary** | The domain aggregate never crosses into HTTP; primitives in (`Command`), primitives out (`Response`). |
| **Two kinds of validation** | Input *shape* at the HTTP boundary (FormRequest → `422`); business *rules* in the domain. |
| **OpenAPI is spec-first** | `docs/openapi.yaml` is the source of truth; the code satisfies it (not generated from annotations, which would couple the contract to the framework). |
| **Dedicated `mysql-test` (tmpfs) for tests** | Integration tests run on the real MySQL engine, isolated from dev, fast and ephemeral. Never the dev DB. |
| **Rule pipeline over classic Chain of Responsibility** | Same extensibility, but it collects *all* validation reasons (no short-circuit) and stays stateless → cacheable and unit-testable. See "Patterns used". |
| **Candidature is immutable; states/bitácora deferred** | Simpler and lets the validation report be cached forever; a history/state lifecycle is a future dedicated capability, not bolted on. |
| **No single transaction around the bulk auto-assign** | The operation only processes *unassigned* candidatures, so it is idempotent and **resumable** (re-running finishes the rest); each `save()` is an atomic upsert and the run is serialized by a lock. One giant transaction would hold locks and bloat at scale — batched commits would be the scale answer, not a mega-transaction. |

## Patterns used

- **Hexagonal (ports & adapters)** — domain/application declare ports (`CandidatureRepository`,
  `ConsolidatedListingReader`, `ReportDispatcher`, `ConsolidatedReportWriter`, `ReportNotifier`,
  `Mutex` …); infrastructure provides the adapters, wired in service providers.
- **DDD tactical patterns** — aggregate roots, value objects (incl. PHP **enums** for the `Report`
  state), domain exceptions, repositories.
- **CQRS (lightweight)** — Eloquent for writes; Query Builder for complex reads returning DTOs.
- **Data Mapper** — mappers isolate the Eloquent models from the domain aggregates.
- **Rule pipeline** (extensible validation) — a collection of independent, stateless rules run over a
  candidature and aggregated into a report; adding a rule is one line in the provider, existing rules
  untouched. *(A stateless relative of Chain of Responsibility — see below.)*
- **Decorator** — caching decorators over the read ports and the assignment repository (transparent
  caching + version-key invalidation), without touching the real implementations.
- **Middleware** — a reusable idempotency middleware (`Idempotency-Key`) for safe retries.
- **Mutex / distributed lock** — serializes the bulk auto-assign (Redis lock behind a `Mutex` port).
- **Queued Job** — asynchronous Excel generation + email notification.
- **State machine** — the `Report` aggregate's guarded lifecycle (`pending → processing →
  completed | failed`), illegal transitions rejected in the domain.

### Validation: rule pipeline vs. classic Chain of Responsibility

The brief suggests Chain of Responsibility. We implemented a **rule pipeline** instead. It meets the
same requirement — *extensible without modifying existing rules* (add a rule in the service provider) —
but is a better fit here because:

- **It collects every result, it doesn't short-circuit.** The endpoint must report *all* the reasons a
  candidature is (in)valid. A classic CoR stops at the first handler that handles the request; our
  pipeline runs **all** rules and aggregates pass/fail. "Report all reasons" beats "stop at the first".
- **Rules are stateless** (each is a pure function of the candidature), so the report is **cacheable**
  and each rule is trivially unit-tested in isolation.

The extensibility that CoR is cited for is fully preserved; we just kept the rules independent instead
of chained.

## Getting started

**Prerequisites:** Docker + Docker Compose. (No local PHP/Composer needed.)

From a fresh clone, one command does everything:

```bash
make setup    # copies .env.example -> .env, builds, starts the stack,
              # generates APP_KEY, and runs migrations + seeders
```

`.env` is git-ignored (it holds secrets); the committed **`.env.example`** has working Docker defaults,
and `make setup` derives `.env` from it and generates the app key — so there is nothing to configure by
hand. Equivalent manual steps:

```bash
cp .env.example .env
make build                       # build the php-fpm 8.4 image
make up                          # start nginx, app, worker, mysql, redis, mailpit, mysql-test
docker compose exec app composer install    # vendor/ is git-ignored — install deps first
docker compose exec app php artisan key:generate
make fresh                       # drop + migrate + seed (25 candidatures, 5 evaluators, 15 assignments)
```

Services:

| Service | URL / Port |
|---|---|
| API | http://localhost:8080 |
| API docs (Swagger UI) | http://localhost:8080/docs |
| Mailpit (captured emails) | http://localhost:8025 |
| MySQL (dev) | `localhost:3306` — db `recruitment`, user `recruitment` / `secret` |
| MySQL (test) | `localhost:3307` — db `recruitment_test` |
| Redis | `localhost:6379` |

## Commands

All commands run inside the containers via `make` (see `make help`):

| Command | Description |
|---|---|
| `make up` / `make down` | Start / stop the stack |
| `make test` | Run the test suite (against `mysql-test`) |
| `make stan` | Static analysis (PHPStan/Larastan level 8) |
| `make pint` / `make pint-fix` | Check / fix code style |
| `make quality` | `pint` + `stan` + `test` |
| `make fresh` | Drop, re-migrate and seed the dev DB |
| `make shell` / `make tinker` | Shell / Tinker in the app container |

## API

Interactive documentation: **http://localhost:8080/docs** (Swagger UI, backed by `docs/openapi.yaml`).
For manual calls from PhpStorm, see `http/candidatures.http`.

### `POST /candidatures`

```bash
curl -X POST http://localhost:8080/candidatures \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{
        "full_name": "Ada Lovelace",
        "email": "ada@example.com",
        "years_of_experience": 7,
        "cv": "Mathematician and first programmer."
      }'
```

| Status | When |
|---|---|
| `201 Created` | Registered; body carries the candidature under `data` (with `id` + `created_at`). |
| `409 Conflict` | A candidature with that email already exists (case-insensitive). |
| `422 Unprocessable Entity` | Missing/malformed input (per-field errors). |

### `GET /candidatures/{id}/validation`

Evaluates a stored candidature's eligibility and reports **why**, with a per-rule breakdown. Rules are
extensible without modifying existing ones (a rule-collection pipeline; see the design notes). Computed
on the fly — nothing is persisted.

```bash
curl http://localhost:8080/candidatures/01J9Z8K3Q7R5X2M4B6T8V0W1C2/validation \
  -H "Accept: application/json"
```

```json
{
  "data": {
    "candidature_id": "01J9Z8K3Q7R5X2M4B6T8V0W1C2",
    "valid": false,
    "rules": [
      { "rule": "has_cv",             "passed": true,  "reason": "The candidature has a CV." },
      { "rule": "valid_email",        "passed": true,  "reason": "The email is valid." },
      { "rule": "minimum_experience", "passed": false, "reason": "Requires at least 2 years of experience; has 1." }
    ]
  }
}
```

| Status | When |
|---|---|
| `200 OK` | Report returned; `valid` is `true` only when every rule passed. |
| `404 Not Found` | No candidature with that id (or a malformed id). |

### `POST /evaluators`

Creates an evaluator (`{ "name": "Grace Hopper" }`). Returns `201` with the evaluator, or `422` for a
missing name.

### `PUT /candidatures/{id}/evaluator`

Assigns an evaluator to a candidature and records the assignment date. One evaluator can handle many
candidatures; a candidature has at most one evaluator (calling it again reassigns).

```bash
curl -X PUT http://localhost:8080/candidatures/{id}/evaluator \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{ "evaluator_id": "01J9ZC5K3Q7R5X2M4B6T8V0W1C2" }'
```

| Status | When |
|---|---|
| `200 OK` | Assigned; body reports the candidature, evaluator and assignment date. |
| `404 Not Found` | The candidature or the evaluator does not exist. |
| `409 Conflict` | The candidature is not eligible (fails its validation rules). *Our own gate — see the change's design.* |
| `422 Unprocessable Entity` | Missing or malformed `evaluator_id`. |

> Assignment reuses the candidature-validation rules: **only eligible candidatures can be assigned**.
> The assignment lives in its own `assignments` table, so the candidature stays immutable.

### `POST /candidatures/auto-assign`

Bulk operation: assigns **every candidature that is both unassigned and eligible** to the
**least-loaded** evaluator, rebalancing as it goes. "Unassigned" is a SQL condition; "eligible" is the
domain validation rules (the single source of truth). Returns a summary.

```bash
curl -X POST http://localhost:8080/candidatures/auto-assign -H "Accept: application/json"
# { "data": { "assigned": 7, "skipped_ineligible": 2 } }
```

| Status | When |
|---|---|
| `200 OK` | Summary of the run (`assigned`, `skipped_ineligible`); `0` assigned when there is nothing to do. |
| `409 Conflict` | There are eligible candidatures to assign but no evaluators exist. |

### `GET /candidatures/consolidated`

The consolidated view: every candidature that has an evaluator, with the evaluator's name, the
assignment date, the **total candidatures** that evaluator handles and the **concatenated emails** of
their candidates. Sortable, filterable and paginated.

```bash
curl "http://localhost:8080/candidatures/consolidated?sort=evaluator_name&direction=asc&filter[full_name]=Ada&per_page=15" \
  -H "Accept: application/json"
```

- **Sort:** `sort=<column>&direction=asc|desc` (default `years_of_experience` desc). Columns:
  `full_name`, `email`, `years_of_experience`, `evaluator_name`, `assigned_at`, `evaluator_total`.
- **Filter:** `filter[<column>]=<value>` — **exact** for number/date, **prefix** (`value%`) for text.
- **Pagination:** `page`, `per_page` (default 15, max 100); response carries `data[]` + `meta{}`.
- **Performance:** the default sort uses an index (backward index scan, no filesort); joins are unique/PK
  lookups; text filters stay index-friendly (no `%contains%`). The per-evaluator aggregate
  (`GROUP_CONCAT`/`COUNT`) is the one heavy part at very large scale — see `docs/scalability-backlog.md`.
- **`422`** on an unknown sort column or invalid params.

### `GET /candidatures/{id}/summary`

One consolidated view of a single candidature: its full data, the `valid` flag, the validation
breakdown (`validations.passed` / `validations.failed`, computed by reusing the rules) and its
`evaluator` (`{ name, assigned_at }`, or `null` if unassigned). The passed/failed split is built with
**Collections** in the HTTP layer.

```bash
curl http://localhost:8080/candidatures/{id}/summary -H "Accept: application/json"
```

| Status | When |
|---|---|
| `200 OK` | The summary (data + validations + evaluator). |
| `404 Not Found` | No candidature with that id. |

### `POST /candidatures/consolidated/export`

Requests an **Excel export** of the consolidated listing (same `sort`/`filter` as the listing, no
pagination). It does not build the file inline: it records a `Report` (status `pending`), schedules a
background job (Redis queue + `worker`) and returns `202` immediately. The job builds the workbook
(**PhpSpreadsheet**, 50 candidates per sheet), stores it, marks the report `completed` and emails a
download link (Mailpit at `http://localhost:8025`). See `http/reports.http` for the full flow.

```bash
curl -X POST "http://localhost:8080/candidatures/consolidated/export" \
  -H "Content-Type: application/json" -H "Accept: application/json" -d '{"sort":"years_of_experience","direction":"desc"}'
# 202 { "data": { "id": "01J...", "status": "pending" } }
```

| Status | When |
|---|---|
| `202 Accepted` | The export was accepted; generation scheduled. |
| `422 Unprocessable Entity` | Invalid `sort` / `direction` / filter. |

### `GET /reports/{id}` and `GET /reports/{id}/download`

Poll the report status and download the file once ready. The report moves
`pending → processing → completed` (or `failed`, with a reason).

```bash
curl http://localhost:8080/reports/{id} -H "Accept: application/json"
# { "data": { "status": "completed", "download_url": "http://localhost:8080/reports/{id}/download", ... } }
curl -L http://localhost:8080/reports/{id}/download -o report.xlsx
```

| Endpoint | Status | When |
|---|---|---|
| `GET /reports/{id}` | `200` / `404` | Status returned / no such report. |
| `GET /reports/{id}/download` | `200` / `409` / `404` | The `.xlsx` / not completed yet / no such report. |

## Testing

**82 tests / 200+ assertions.** Philosophy: **no internal mocks** — our own classes are exercised for
real; only **external boundaries** are faked, and always with Laravel's own fakes.

- **Unit tests** (`tests/Unit`) — pure PHP, no framework, no DB. Value objects, the validation rule
  pipeline, and use cases against **in-memory fakes** (`InMemory*Repository`, `FakePendingAssignmentReader`,
  `ImmediateMutex`) and an **object mother** (`CandidatureMother`) — real implementations, not mocks.
- **Integration tests** (`tests/Feature`) — the full app against the real **`mysql-test`** database
  (`RefreshDatabase`), so the complex SQL (`GROUP_CONCAT`, joins) runs on the real engine. Never the
  dev DB. External boundaries are faked: **`Mail::fake`** (SMTP), **`Storage::fake`** (filesystem),
  **`Bus::fake`** (queue) where a test needs to observe dispatch; the queue otherwise runs on the
  `sync` driver so real jobs/use cases execute end to end.
- **Caching caveat learned the hard way:** the caching tests also assert against real Redis, because
  the `array` cache store doesn't serialize and once masked a cache-hit bug — see
  `docs/performance-notes.md`.

```bash
make test        # or: make quality  (pint + phpstan L8 + tests)
```

## Scalability

The infrastructure for horizontal scaling is in place and used as capabilities land:

- **Redis** — cache and queue backend.
- **Response caching** — the consolidated listing and the validation report are served from Redis via
  caching decorators over their read ports. The listing is invalidated with a **version-key** bumped on
  every assignment write (O(1), no key scan); the validation report uses a long TTL (candidatures are
  immutable). TTLs live in `config/performance.php`. Measured ~588× faster on a cache hit — see
  `docs/performance-notes.md`.
- **Queue worker** — a dedicated container (`queue:work redis --tries=3 --backoff=5`) for async work
  (used by the Excel report generation + email notification).
- **Idempotency** — write endpoints without a natural key support safe retries via an optional
  `Idempotency-Key` header (a reusable middleware stores/replays the response in Redis; a lock guards
  concurrent same-key requests → `409`, and a key reused with a different body → `422`). Applied to the
  export; `POST /candidatures` is already idempotent via its unique email.
- **Concurrency** — single assignments are race-safe via the `assignments.candidature_id` unique
  index; the **bulk auto-assign** runs under an exclusive Redis lock (a `Mutex` port), so only one runs
  at a time and a concurrent request gets `409`.
- **Deep pagination** — the OFFSET cost on deep pages is measured (~118 ms at `OFFSET 7000`) and the
  keyset/cursor fix is **designed and consciously deferred** (realistic sizes don't need it yet) —
  see `docs/performance-notes.md`.
- **Mailpit** — captures outgoing mail in development.

## Roadmap

This repo is built capability by capability (Spec-Driven Development; see `openspec/`).

| # | Capability | Status |
|---|---|---|
| 1 | `candidature-registration` | ✅ Implemented |
| 2 | `candidature-validation` (extensible rule pipeline) | ✅ Implemented |
| 3 | `evaluator-management` + `evaluator-assignment` | ✅ Implemented |
| + | `auto-assignment` (least-loaded bulk, beyond the brief) | ✅ Implemented |
| 4 | `consolidated-listing` (complex SQL) | ✅ Implemented |
| 5 | `candidature-summary` (Collections) | ✅ Implemented |
| 6 | `excel-report` (queue + email + PhpSpreadsheet) | ✅ Implemented |
| 7 | `scalability` hardening (caching ✓ · idempotency ✓ · locks ✓ · keyset △ designed/deferred) | ✅ Implemented |
