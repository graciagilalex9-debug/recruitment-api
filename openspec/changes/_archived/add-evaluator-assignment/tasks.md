# Tasks — add-evaluator-assignment

Capabilities: **evaluator-management** + **evaluator-assignment**. Built inside-out. The `Candidature`
aggregate is not modified. Every scenario is covered by a test (see §10).

## 1. Domain — Evaluator context (pure PHP)
- [x] 1.1 `Evaluator/Domain/ValueObject/EvaluatorId` — ULID (validates, equality).
- [x] 1.2 `Evaluator/Domain/ValueObject/EvaluatorName` — non-empty (trimmed).
- [x] 1.3 `Evaluator/Domain/Evaluator` — aggregate (id, name, createdAt); named ctors `register`/`reconstitute`.
- [x] 1.4 `Evaluator/Domain/EvaluatorRepository` — port: `nextIdentity`, `findById`, `save`.
- [x] 1.5 `Evaluator/Domain/Exception/{InvalidEvaluatorId, InvalidEvaluatorName, EvaluatorNotFound}`.

## 2. Application — Evaluator
- [x] 2.1 `Evaluator/Application/Register/RegisterEvaluatorCommand` — input DTO (name).
- [x] 2.2 `Evaluator/Application/Register/EvaluatorResponse` — output DTO; `fromEvaluator()`.
- [x] 2.3 `Evaluator/Application/Register/EvaluatorCreator` — build aggregate; save; return DTO.

## 3. Domain — Assignment context (pure PHP)
- [x] 3.1 `Assignment/Domain/Assignment` — aggregate (candidatureId, evaluatorId, assignedAt);
  named ctors `assign`/`reconstitute`.
- [x] 3.2 `Assignment/Domain/AssignmentRepository` — port: `save` (upsert by candidature),
  `findByCandidature(CandidatureId): ?Assignment`.
- [x] 3.3 `Assignment/Domain/Exception/CandidatureNotEligible` (carries the candidature id) — for the
  eligibility gate (our decision, see design §2).

## 4. Application — Assignment
- [x] 4.1 `Assignment/Application/Assign/AssignmentResponse` — output DTO (candidatureId, evaluatorId,
  assignedAt).
- [x] 4.2 `Assignment/Application/Assign/EvaluatorAssigner` — verify candidature exists
  (`CandidatureRepository.findById` → `CandidatureNotFound`) and evaluator exists
  (`EvaluatorRepository.findById` → `EvaluatorNotFound`); run `CandidatureValidator` and throw
  `CandidatureNotEligible` if not valid (the eligibility gate); build `Assignment`; `save`; return DTO.

## 5. Infrastructure — Persistence
- [x] 5.1 Migration `create_evaluators_table` — `id char(26)` PK, `name`, `created_at`.
- [x] 5.2 Migration `create_assignments_table` — `id char(26)` PK, `candidature_id char(26)` **unique**
  + FK, `evaluator_id char(26)` **indexed** + FK, `assigned_at`. Verified on dev DB.
- [x] 5.3 `Evaluator/Infrastructure/Persistence` — `EvaluatorModel` (+ `EvaluatorFactory`),
  `EvaluatorMapper`, `EloquentEvaluatorRepository` (nextIdentity, findById, save).
- [x] 5.4 `Assignment/Infrastructure/Persistence` — `AssignmentModel` (HasUlids surrogate id),
  `AssignmentMapper`, `EloquentAssignmentRepository` (`save` = `updateOrCreate` by `candidature_id`).

## 6. Infrastructure — HTTP
- [x] 6.1 `Evaluator/Infrastructure/Http` — `RegisterEvaluatorRequest` (name required|string|max:255),
  `PostEvaluatorController`, `EvaluatorResource`.
- [x] 6.2 `Assignment/Infrastructure/Http` — `AssignEvaluatorRequest` (`evaluator_id` required|ulid),
  `PutCandidatureEvaluatorController`, `AssignmentResource`.
- [x] 6.3 Map `EvaluatorNotFound` → `404` and `CandidatureNotEligible` → `409` in `bootstrap/app.php`
  (`CandidatureNotFound` already mapped).
- [x] 6.4 Routes: `POST /evaluators`; `PUT /candidatures/{id}/evaluator` (`->whereUlid('id')`).

## 7. DI wiring
- [x] 7.1 `EvaluatorServiceProvider` (bind `EvaluatorRepository`) + register in `bootstrap/providers.php`.
- [x] 7.2 `AssignmentServiceProvider` (bind `AssignmentRepository`) + register in `bootstrap/providers.php`.

## 8. API contract
- [x] 8.1 Add `POST /evaluators` and `PUT /candidatures/{id}/evaluator` to `docs/openapi.yaml`.

## 9. Data
- [x] 9.1 `EvaluatorFactory` + `EvaluatorSeeder`; registered in `DatabaseSeeder`.

## 10. Tests (real mysql-test, no internal mocks) — 16 new, suite green at 39
- [x] 10.1 Unit — `EvaluatorName` rejects empty. In-memory fakes `InMemoryEvaluatorRepository`,
  `InMemoryAssignmentRepository` in `Tests\Support`.
- [x] 10.2 Unit — `EvaluatorAssigner`: assigns; reassign replaces; throws `CandidatureNotFound` /
  `EvaluatorNotFound` / `CandidatureNotEligible`. *(covers: 404 + eligibility + reassign)*
- [x] 10.3 Feature — `POST /evaluators` valid → `201` + row.
- [x] 10.4 Feature — `POST /evaluators` no name → `422`, nothing persisted.
- [x] 10.5 Feature — `PUT …/evaluator` valid → `200` + assignment row.
- [x] 10.6 Feature — same evaluator on two candidatures → both `200`.
- [x] 10.7 Feature — reassign a candidature → `200`, one current assignment.
- [x] 10.8 Feature — missing candidature → `404`; missing evaluator → `404`.
- [x] 10.9 Feature — missing / non-ULID `evaluator_id` → `422`.
- [x] 10.10 Feature — ineligible candidature → `409`, no assignment. *(the eligibility gate)*

## 11. Validation and cleanup
- [x] 11.1 `make quality` (Pint + PHPStan L8 + tests) green.
- [x] 11.2 README: document `POST /evaluators` and `PUT /candidatures/{id}/evaluator`; roadmap update.
