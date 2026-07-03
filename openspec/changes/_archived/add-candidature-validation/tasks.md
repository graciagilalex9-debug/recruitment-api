# Tasks — add-candidature-validation

Capability: **candidature-validation**. Built inside-out (Domain → Application → Infrastructure →
HTTP → tests). Reuses the `Candidature` aggregate. Every scenario is covered by a test (see §7).

## 1. Domain — validation (pure PHP)
- [x] 1.1 `Domain/Validation/RuleResult` — VO: `key`, `passed`, `reason`; named ctors `passed()`/
  `failed()`, accessor `hasPassed()` (renamed to avoid clashing with the `passed()` factory).
- [x] 1.2 `Domain/Validation/ValidationReport` — VO holding `list<RuleResult>`; `isValid()` (all
  passed, via `array_all`), `results()`.
- [x] 1.3 `Domain/Validation/ValidationRule` — interface: `evaluate(Candidature): RuleResult`.
- [x] 1.4 `Domain/Validation/Rule/MustHaveCv`, `MustHaveValidEmail`, `MustHaveMinimumExperience`
  (>= 2 years) — each implements `ValidationRule` with its key + reason.
- [x] 1.5 `Domain/Validation/CandidatureValidator` — runs an ordered `list<ValidationRule>`,
  collects each `RuleResult` into a `ValidationReport` (stateless).
- [x] 1.6 Extend `Domain/CandidatureRepository` with `findById(CandidatureId): ?Candidature`
  (implemented in both the Eloquent repo and the in-memory test fake).
- [x] 1.7 `Domain/Exception/CandidatureNotFound` (carries the id).

## 2. Application — validate use case
- [x] 2.1 `Application/Validate/ValidationReportResponse` (+ nested `RuleResultResponse`) — output DTO
  (primitives: `candidatureId`, `valid`, list of rule results); `fromReport()` maps domain → primitives.
- [x] 2.2 `Application/Validate/CandidatureValidationFinder` — `findById` (throw `CandidatureNotFound`
  if null) → run `CandidatureValidator` → map to `ValidationReportResponse`.

## 3. Infrastructure — Persistence
- [x] 3.1 Implement `EloquentCandidatureRepository::findById` — `CandidatureModel::find` + reuse
  `CandidatureMapper::toDomain`; return null when absent.

## 4. Infrastructure — HTTP
- [x] 4.1 `Infrastructure/Http/ValidationReportResource` (JsonResource) — snake_case JSON under `data`
  (`candidature_id`, `valid`, `rules[]` with `rule`/`passed`/`reason`).
- [x] 4.2 `Infrastructure/Http/GetCandidatureValidationController` — thin: call the finder, return
  `200` with the resource.
- [x] 4.3 Map `CandidatureNotFound` → `404 Not Found` (exception handler in `bootstrap/app.php`).
- [x] 4.4 Register route `GET /candidatures/{id}/validation` in `routes/api.php` (`->whereUlid('id')`
  → malformed id yields a 404 route miss).

## 5. DI wiring
- [x] 5.1 In `CandidatureServiceProvider`, assemble the ordered rule list and bind
  `CandidatureValidator` (stateless singleton). Adding a rule later = one line here.

## 6. API contract
- [x] 6.1 Add `GET /candidatures/{id}/validation` to `docs/openapi.yaml` (`200` breakdown + `404`).

## 7. Tests (real `mysql-test` DB via RefreshDatabase, no internal mocks)
- [x] 7.1 Unit — `MustHaveMinimumExperience`: passes at 2 years, fails at 1 (with reason). Plus a
  smoke test of `MustHaveCv` / `MustHaveValidEmail`. (Uses `Tests\Support\CandidatureMother`.)
- [x] 7.2 Unit — `CandidatureValidator`: aggregates results; `isValid()` true only when all rules pass.
- [x] 7.3 Feature — `GET /candidatures/{id}/validation` for an eligible candidature → `200`,
  `valid: true`, all rules passed. *(covers: An eligible candidature passes every rule)*
- [x] 7.4 Feature — for a candidature with < 2 years → `200`, `valid: false`, minimum-experience rule
  failed with a reason, others passed. *(covers: An ineligible candidature reports which rule failed)*
- [x] 7.5 Feature — unknown id → `404`. *(covers: Validating a non-existent candidature)*

## 8. Validation and cleanup
- [x] 8.1 `make quality` (Pint + PHPStan L8 + tests) green.
- [x] 8.2 README: document the `GET /candidatures/{id}/validation` endpoint + roadmap update.
