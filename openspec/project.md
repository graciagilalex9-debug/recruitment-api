# Project context — Recruitment API

## Identity

- **Repo**: `recruitment-api`
- **Type**: REST API to manage **candidatures** and **evaluators** (Senior Backend Laravel technical test; learning exercise).
- **Stack**: Laravel 13 on PHP 8.4, **Hexagonal + DDD** architecture. Everything runs in Docker (no local PHP).

## Purpose

Manage the lifecycle of job candidatures and their assignment to evaluators: registering candidatures, validating their eligibility, assigning them to an evaluator who handles several, high-performance consolidated listings, and background Excel report generation. The real goal of this repo is to **learn Laravel** while applying clean architecture from day one, treating Laravel as an infrastructure detail rather than the core.

> **Language policy**: all repository artifacts — code, specs, docs, commit messages, comments — are written in **English**, even though the maintainers converse in Spanish.

## Guiding principle

**Laravel is an infrastructure DETAIL, not the core.** Dependencies always point inward:

```
Domain  ←  Application  ←  Infrastructure
```

The domain is pure PHP, with no Laravel dependency whatsoever. The golden rule of decoupling: the **Eloquent model** lives ONLY in `Infrastructure\Persistence` and is NOT the domain entity; a **mapper** sits between the Eloquent model and the domain aggregate. Goal: be able to replace the data layer without rewriting business logic.

## Architecture (layers and responsibilities)

```
Infrastructure  (Laravel = detail)
  ├─ Http/         Controllers, FormRequests, Resources (API JSON)
  ├─ Persistence/  Eloquent repositories (writes), Query Builder readers (reads),
  │                Eloquent models (NOT domain entities) + mappers
  ├─ Queue/        Jobs (Excel report, email)
  ├─ Providers/    interface -> implementation binding (Service Provider)
  └─ Console/      artisan commands
Application  (use cases)
  └─ orchestrate the domain; one class per use case (Register/Validate/Assign...)
Domain  (pure PHP, ZERO Laravel)
  ├─ Aggregates (Candidature, Evaluator), Value Objects (Email, YearsOfExperience...)
  ├─ Validation/  rules + Chain of Responsibility (extensible without touching existing rules)
  └─ Repository/Reader interfaces, domain events, exceptions
```

### Lightweight CQRS

- **Writes**: repositories implemented with Eloquent (behind the domain interface).
- **Reads / complex queries** (e.g. consolidated listing): direct **Query Builder**, returning **DTOs**, without going through the domain.

## Naming conventions

- **Application**: `<Entity><Action>er` — e.g. `CandidatureRegistrar`, `CandidatureValidator`, `EvaluatorAssigner`. Consistency above all.
- **Controllers**: `<HttpVerb><Entity>` — e.g. `PostCandidature`, `GetConsolidatedList`.
- **Repositories**: interface `<Entity>Repository` in Domain + implementation `Eloquent<Entity>Repository` in Infrastructure.
- **Readers (reads)**: `<Entity>Reader` / Query Builder-based readers.

## API contract

- `docs/openapi.yaml` is the **source of truth** for the HTTP contract (spec-first). The code is
  implemented to satisfy it; it is NOT generated from controller annotations (that would couple the
  contract to the framework). Each capability adds its endpoints to the same file.
- Swagger UI is served at `GET /docs` (a Blade view loading `swagger-ui-dist`), reading the contract
  from `GET /openapi.yaml` (backed by `docs/openapi.yaml`) — no annotation-based tooling.

## Stack and environment (all Docker, compose written by hand)

Services in `docker-compose.yml`:

- **nginx** (`:8080`) → **app** (php-fpm 8.4)
- **worker** — `queue:work redis` (Excel report + async email; `--tries=3 --backoff=5`)
- **mysql 8.4** — development database (writes via Eloquent)
- **redis 7** — cache + queue backend
- **mailpit** — SMTP (`:1025`) + web UI (`:8025`)

Databases: **MySQL** in development, a dedicated **`mysql-test`** service (data on tmpfs, ephemeral)
in tests. Cache and queues: **Redis**. Mail: **SMTP → Mailpit**.

All commands run inside the containers, e.g.:

```
docker compose exec app php artisan ...
docker compose exec app php artisan test
```

## Testing

- **PHPUnit** (`Unit` and `Feature` suites; `tests/Unit`, `tests/Feature`).
- Philosophy: **no internal mocks**; only external boundaries are mocked. Integration tests run
  against a **REAL MySQL database** — the isolated `mysql-test` service (tmpfs), configured in
  `phpunit.xml`. **Never** the dev DB. SQLite is not used (the app's real engine is MySQL, and the
  complex SQL must be tested on it). Pure unit tests use in-memory fakes and touch no database.
- Minimums required by the test: **4 unit tests** (including the Chain of Responsibility and the validation), **1 endpoint test** for a complex feature, and **1 integration test** against a real DB.

## Planned capabilities

Each capability is **one OpenSpec change** (not one spec per class). Inside each change, `tasks.md` breaks down the steps.

1. **candidature-registration** — register a candidature (full name, email, years of experience, CV text, creation date; optional activity log). The foundational vertical slice.
2. **candidature-validation** — decide whether a candidature is valid and why; **Chain of Responsibility**; criteria: has CV, valid email, >= 2 years of experience; extensible without modifying existing rules.
3. **evaluator-assignment** — an evaluator manages N candidatures; assignment date; change history; concurrency in bulk assignments.
4. **consolidated-listing** — listing of candidatures with their evaluator: joins, `COUNT` per evaluator, concatenated list of emails (`GROUP_CONCAT`), dynamic ordering by any column (default years of experience desc), filtering by any column, pagination; efficient under high load.
5. **candidature-summary** — summary: full data + passed/failed validations + evaluator + status; use of Collections.
6. **excel-report** — Excel report (50 candidates per sheet) of the consolidated listing, via an external library (**PhpSpreadsheet**), generated on a **queue** and notified by **email** on completion.
7. **scalability** — cross-cutting: cache, queues, idempotency, concurrency; high performance and horizontal scalability.

## Working method with OpenSpec in this repo

- **Granularity**: ONE change per capability (not one per file). Fine-grained steps go in `tasks.md`.
- **Flow**: `openspec-spec` (define) → ✋ human validation → **MANUAL implementation task by task** (no automatic generators) → `openspec-archive`.
- Implementation is written **by hand** to learn Laravel; OpenSpec only guides the design.
- `specs/` only grows when implemented changes are archived (spec-on-touch); it is never backfilled.

## Important constraints

- No local PHP: every command (`artisan`, `composer`, `phpunit`) runs inside the Docker containers.
- The domain (`Domain`) must not import anything from Laravel or Eloquent.
- The Eloquent model never leaves `Infrastructure\Persistence`; Application/HTTP receive domain aggregates (writes) or DTOs (reads), never Eloquent models.

## External dependencies (read-only)

- **Laravel 13** / PHP 8.4 (framework, as an infrastructure detail).
- **PhpSpreadsheet** — Excel report generation (`excel-report` capability).
- **Redis** (cache + queues), **MySQL 8.4** (dev), **SQLite** (tests), **Mailpit** (dev SMTP).
