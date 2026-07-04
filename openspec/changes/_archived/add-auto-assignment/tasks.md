# Tasks — add-auto-assignment

Capability: **auto-assignment**. Reuses the `Assignment` context, the `CandidatureValidator` (#2) and
the assignments upsert (#3). Built inside-out. Every scenario is covered by a test (see §5).

## 1. Domain — Assignment context (read side)
- [x] 1.1 `Assignment/Domain/PendingAssignmentReader` — port: `unassignedCandidatures(): list<Candidature>`,
  `evaluatorLoads(): array<string, int>` (evaluatorId => current assignment count, includes zero).
- [x] 1.2 `Assignment/Domain/Exception/NoEvaluatorsAvailable` (→ 409).

## 2. Application — AutoAssign
- [x] 2.1 `Assignment/Application/AutoAssign/AutoAssignmentResponse` — DTO `{ assigned, skippedIneligible }`.
- [x] 2.2 `Assignment/Application/AutoAssign/CandidatureAutoAssigner` — load loads + unassigned; filter
  by `CandidatureValidator`; if eligible exist and loads empty → throw `NoEvaluatorsAvailable`; assign
  each eligible to the min-load evaluator with a live running tally; return the summary DTO.

## 3. Infrastructure
- [x] 3.1 `Assignment/Infrastructure/Persistence/QueryBuilderPendingAssignmentReader` — `whereNotExists`
  against `assignments` for unassigned candidatures (mapped via `CandidatureMapper`); `LEFT JOIN` +
  `COUNT`/`GROUP BY` for evaluator loads.
- [x] 3.2 `Assignment/Infrastructure/Http/PostCandidatureAutoAssignController` + `AutoAssignmentResource`
  (snake_case `{ assigned, skipped_ineligible }` under `data`).
- [x] 3.3 Route `POST /candidatures/auto-assign` in `routes/api.php`.
- [x] 3.4 Map `NoEvaluatorsAvailable` → `409` in `bootstrap/app.php`.
- [x] 3.5 DI: bind `PendingAssignmentReader` → `QueryBuilderPendingAssignmentReader` in
  `AssignmentServiceProvider`.

## 4. API contract
- [x] 4.1 Add `POST /candidatures/auto-assign` to `docs/openapi.yaml` (`200` summary + `409`).

## 5. Tests (real mysql-test, no internal mocks)
- [x] 5.1 Unit — `CandidatureAutoAssigner` with in-memory fakes + a fake reader: distributes to the
  least-loaded; skips ineligible; leaves already-assigned untouched; throws `NoEvaluatorsAvailable`
  when eligible exist but no evaluators. *(covers: least-loaded + skip + untouched + 409 at use-case level)*
- [x] 5.2 Feature — assigns every unassigned eligible candidature, balanced across evaluators → `200`
  with the assigned count. *(covers: Every unassigned eligible candidature is assigned; least-loaded)*
- [x] 5.3 Feature — ineligible unassigned candidatures get no evaluator; summary counts them.
  *(covers: Ineligible candidatures are skipped)*
- [x] 5.4 Feature — an already-assigned candidature keeps its evaluator. *(covers: left untouched)*
- [x] 5.5 Feature — nothing to assign → `200`, `assigned: 0`. *(covers: Nothing to assign)*
- [x] 5.6 Feature — eligible candidatures but empty evaluators table → `409`, no assignment recorded.
  *(covers: Eligible candidatures but no evaluators)*

## 6. Validation and cleanup
- [x] 6.1 `make quality` (Pint + PHPStan L8 + tests) green.
- [x] 6.2 README: document `POST /candidatures/auto-assign`; roadmap update.
