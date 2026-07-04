# Tasks — add-candidature-summary

Capability: **candidature-summary**. A read that composes #2 (validation) and #3 (assignment/evaluator).
Every scenario is covered by a test (see §4).

## 1. Application
- [x] 1.1 `Candidature/Application/Summary/CandidatureSummary` — DTO: candidature fields, `valid`,
  `rules` (list of {rule, passed, reason}), `evaluatorName?`, `assignedAt?` (pure PHP, no Collections).
- [x] 1.2 `Candidature/Application/Summary/CandidatureSummaryFinder` — `findById` (→ `CandidatureNotFound`),
  run `CandidatureValidator`, look up assignment → evaluator (optional); build the DTO.

## 2. Infrastructure — HTTP
- [x] 2.1 `Candidature/Infrastructure/Http/CandidatureSummaryResource` — shapes JSON; uses Collections
  to split `rules` into `validations.passed` / `validations.failed`; evaluator object or null.
- [x] 2.2 `Candidature/Infrastructure/Http/GetCandidatureSummaryController` — call the finder, return
  the shaped JSON.
- [x] 2.3 Route `GET /candidatures/{id}/summary` (`->whereUlid('id')`).

## 3. DI
- [x] 3.1 No new binding needed — the finder auto-resolves from already-bound ports (candidature repo,
  validator, assignment repo, evaluator repo). Verify it resolves.

## 4. Tests (real mysql-test, no internal mocks)
- [x] 4.1 Feature — summary of a valid, assigned candidature: `200`, full data, `valid: true`, all
  rules passed, evaluator present. *(covers: valid, assigned)*
- [x] 4.2 Feature — summary of an ineligible, unassigned candidature: `200`, `valid: false`, failing
  rule under failed, `evaluator: null`. *(covers: ineligible, unassigned)*
- [x] 4.3 Feature — unknown id → `404`. *(covers: missing candidature)*

## 5. Validation and cleanup
- [x] 5.1 `make quality` green.
- [x] 5.2 OpenAPI + README (endpoint + roadmap).
