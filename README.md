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
│  ├─ Http/         Controllers, FormRequests, Resources        │
│  ├─ Persistence/  Eloquent repositories + models + mappers    │
│  ├─ Providers/    interface → implementation binding          │
│  └─ (Queue/, Console/ …)                                      │
├──────────────────────────────────────────────────────────────┤
│  Application  (use cases)                                      │
│  └─ one class per use case; orchestrates the domain           │
├──────────────────────────────────────────────────────────────┤
│  Domain  (pure PHP, ZERO Laravel)                             │
│  ├─ Aggregates, Value Objects                                 │
│  ├─ Chain-of-Responsibility validation (planned)             │
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
- **Reads / complex queries** (e.g. the upcoming consolidated listing) use the **Query Builder**
  directly, returning DTOs, without going through the domain.

## Project structure

```
app/Candidature/
├─ Domain/                          pure PHP, no framework
│  ├─ Candidature.php                   aggregate root
│  ├─ CandidatureRepository.php         port (interface)
│  ├─ ValueObject/                      CandidatureId, Email, YearsOfExperience, FullName, Cv
│  └─ Exception/                        domain exceptions
├─ Application/
│  └─ Register/                         Command, Registrar (use case), Response DTO
└─ Infrastructure/
   ├─ Http/                             PostCandidatureController, RegisterCandidatureRequest, Resource
   ├─ Persistence/                      CandidatureModel, CandidatureMapper, EloquentCandidatureRepository
   └─ Providers/                        CandidatureServiceProvider (DI binding)

docker/            Dockerfile (php-fpm 8.4) + nginx config
docs/openapi.yaml  HTTP contract (source of truth, spec-first)
http/              PhpStorm HTTP Client requests (manual, hit the dev DB)
openspec/          Spec-Driven Development artifacts (project.md, changes/)
tests/             Unit (pure) + Feature (integration, real MySQL)
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

## Patterns used

- **Hexagonal (ports & adapters)** — `CandidatureRepository` is a port; `EloquentCandidatureRepository`
  is the adapter, wired in a service provider.
- **DDD tactical patterns** — aggregate root, value objects, domain exceptions, repository.
- **CQRS (lightweight)** — Eloquent for writes, Query Builder for complex reads.
- **Data Mapper** — `CandidatureMapper` isolates persistence from the domain.
- **Chain of Responsibility** — planned for extensible candidature validation.

## Getting started

**Prerequisites:** Docker + Docker Compose. (No local PHP/Composer needed.)

```bash
cp .env.example .env          # the committed .env already has sane Docker defaults
make build                    # build the php-fpm 8.4 image
make up                       # start nginx, app, worker, mysql, redis, mailpit, mysql-test
make migrate                  # create the schema on the dev database
make fresh                    # (optional) drop + migrate + seed 25 sample candidatures
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

## Testing

Philosophy: **no internal mocks** — only external boundaries would be mocked (there are none yet).

- **Unit tests** (`tests/Unit`) — pure PHP, no framework, no DB. Value objects and the use case
  (against an in-memory *fake* repository — a real implementation, not a mock).
- **Integration tests** (`tests/Feature`) — the full app against the real **`mysql-test`** database
  (`RefreshDatabase`: migrate once, each test in a rolled-back transaction). Never the dev DB.

```bash
make test
```

## Scalability

The infrastructure for horizontal scaling is in place and used as capabilities land:

- **Redis** — cache and queue backend.
- **Queue worker** — a dedicated container (`queue:work redis --tries=3 --backoff=5`) for async work
  (the upcoming Excel report + email notification).
- **Idempotency & concurrency** — email uniqueness is already race-safe via the DB unique constraint;
  bulk-assignment concurrency and idempotency are addressed in later capabilities.
- **Mailpit** — captures outgoing mail in development.

## Roadmap

This repo is built capability by capability (Spec-Driven Development; see `openspec/`).

| # | Capability | Status |
|---|---|---|
| 1 | `candidature-registration` | ✅ Implemented |
| 2 | `candidature-validation` (extensible rule pipeline) | ✅ Implemented |
| 3 | `evaluator-management` + `evaluator-assignment` | ✅ Implemented |
| 4 | `consolidated-listing` (complex SQL) | ⬜ Planned |
| 5 | `candidature-summary` (Collections) | ⬜ Planned |
| 6 | `excel-report` (queue + email) | ⬜ Planned |
| 7 | `scalability` hardening | ⬜ Planned |
