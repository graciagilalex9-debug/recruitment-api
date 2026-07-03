# Tasks — add-candidature-registration

Capability: **candidature-registration**. Built inside-out (Domain → Application → Infrastructure →
HTTP → tests). Every scenario in the spec delta is covered by at least one task (see §8).

## 1. Pre-work — tooling (one-time, repo-wide)
- [x] 1.1 Align `composer.json` `php` constraint to `^8.4` (matches the Docker runtime).
- [x] 1.2 Add code-quality tooling: **Laravel Pint** (`pint.json`, strict_types) and
  **Larastan/PHPStan** (`phpstan.neon`, level 8) + a **`Makefile`** (`make pint/stan/test/quality`).
- [x] 1.3 Dedicated `mysql-test` service (tmpfs) + `phpunit.xml` wired to it — DONE (repo infra).

## 2. Domain — candidature-registration (pure PHP, no Illuminate imports)
- [x] 2.1 `Domain/ValueObject/CandidatureId` — ULID identity; validates canonical ULID, equality,
  immutable. (Generation lives in the repository's `nextIdentity()`, keeping the VO Laravel-free.)
- [x] 2.2 `Domain/ValueObject/Email` — validates format, rejects malformed (throws `InvalidEmail`),
  normalizes (trim + lowercase), exposes the normalized value.
- [x] 2.3 `Domain/ValueObject/YearsOfExperience` — integer `>= 0`, rejects negatives.
- [x] 2.4 `Domain/ValueObject/FullName` and `Domain/ValueObject/Cv` — non-empty (trimmed).
- [x] 2.5 Per-VO domain exceptions (`InvalidCandidatureId`, `InvalidEmail`, `InvalidYearsOfExperience`,
  `InvalidFullName`, `InvalidCv`). `CandidatureAlreadyExists` comes with the persistence step (§4).
- [x] 2.6 `Domain/Candidature` aggregate root — composed from VOs + `createdAt`; named constructors
  `register(...)` / `reconstitute(...)`; immutable, read-only accessors.
- [x] 2.7 `Domain/CandidatureRepository` interface — `nextIdentity()`, `existsByEmail()`, `save()`.

## 3. Application — Register use case
- [x] 3.1 `Application/Register/RegisterCandidatureCommand` — input DTO (primitives).
- [x] 3.2 `Application/Register/CandidatureResponse` — output DTO (primitives, incl. `id`, `createdAt`);
  `fromCandidature()` maps domain → primitives.
- [x] 3.3 `Application/Register/CandidatureRegistrar` — `existsByEmail` check → throw
  `CandidatureAlreadyExists`; build aggregate; `save`; return `CandidatureResponse`.

## 4. Infrastructure — Persistence (Eloquent, writes)
- [x] 4.1 Migration `create_candidatures_table` — `id char(26)` PK (ULID), `full_name`, `email`
  **unique**, `years_of_experience` unsigned smallint, `cv` text, `created_at`. Verified on dev DB.
- [x] 4.2 `Infrastructure/Persistence/CandidatureModel` — Eloquent model (string ULID key, no
  auto-timestamps, `@property` types, `$fillable`); stays inside this folder.
- [x] 4.3 `Infrastructure/Persistence/CandidatureMapper` — aggregate ↔ model/row (both directions).
- [x] 4.4 `Infrastructure/Persistence/EloquentCandidatureRepository` — implements the domain
  interface; `save` catches a UNIQUE(email) violation (SQLSTATE 23000) → `CandidatureAlreadyExists`.

## 5. Infrastructure — HTTP
- [x] 5.1 `Infrastructure/Http/RegisterCandidatureRequest` (FormRequest) — rules: `full_name`
  required|string|max:255, `email` required|email|max:255, `years_of_experience` required|integer|min:0,
  `cv` required|string. (No `unique` rule — uniqueness is a domain concern.)
- [x] 5.2 `Infrastructure/Http/PostCandidatureController` (+ `CandidatureResource` for the JSON shape) —
  thin: build command, call registrar, return `201` with the DTO under `data`.
- [x] 5.3 Map `CandidatureAlreadyExists` → `409 Conflict` (exception handler in `bootstrap/app.php`).
- [x] 5.4 Register `routes/api.php` and wire it in `bootstrap/app.php` with an empty API prefix so the
  route is `POST /candidatures` (no Sanctum).

## 6. DI wiring
- [x] 6.1 Bind `CandidatureRepository` → `EloquentCandidatureRepository` in a service provider
  (`CandidatureServiceProvider`, registered in `bootstrap/providers.php`). Verified via container.

## 7. API contract + data
- [x] 7.1 Create `docs/openapi.yaml` (OpenAPI 3.1) with `POST /candidatures`: request schema, `201`,
  `422`, `409` responses. This file is the HTTP contract source of truth.
- [x] 7.2 `CandidatureFactory` (Eloquent factory, wired via `newFactory()`) + `CandidatureSeeder`
  (25 samples) + registered in `DatabaseSeeder`. Verified: `make fresh` seeds the dev DB.
- [x] 7.3 Serve Swagger UI at `GET /docs` (Blade view + swagger-ui-dist) reading `GET /openapi.yaml`
  (backed by `docs/openapi.yaml`). Verified: both endpoints return 200.

## 8. Tests (real `mysql-test` DB via RefreshDatabase, no internal mocks)
- [x] 8.1 Unit — `Email` VO: rejects malformed/empty, normalizes case, equality. *(covers:
  normalized-email, malformed-email scenarios at the domain level)*
- [x] 8.2 Unit — `YearsOfExperience` VO: accepts 0/positive, rejects negatives. *(covers: negative-years)*
- [x] 8.3 Unit — `CandidatureRegistrar` with an in-memory fake repository (`Tests\Support`): registers;
  throws `CandidatureAlreadyExists` when the email exists. *(covers: valid-registration, email-exists)*
- [x] 8.4 Feature — `POST /candidatures` valid → `201`, body under `data` with `id` + `created_at`,
  row persisted. *(covers: A valid candidature is registered)*
- [x] 8.5 Feature — email with uppercase → `201`, stored/returned lowercase. *(covers: stored email
  normalized)*
- [x] 8.6 Feature — duplicate email → `409`, no second row; and duplicate differing only in case →
  `409`. *(covers: email-exists, email-exists-case-insensitive)*
- [x] 8.7 Feature — missing required field → `422`; malformed email → `422`; negative years → `422`;
  nothing persisted. *(covers: required-field-missing, malformed-email, negative-years)*

## 9. Validation and cleanup
- [x] 9.1 Run the suite + Pint + PHPStan inside the container; all green (`make quality`). Removed the
  default `ExampleTest` files.
- [x] 9.2 README: architecture, layer diagram, structure, decisions, patterns, how to run (Docker,
  queues, cache), API + Swagger link.
