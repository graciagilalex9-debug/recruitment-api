# Design — add-auto-assignment

A bulk operation that clears the backlog: assign every unassigned, eligible candidature to the
least-loaded evaluator. Reuses the `Assignment` context; adds a read side and one use case. An
enhancement beyond the brief, connected to PDF #7 ("concurrencia en asignaciones masivas").

## 1. Architecture

Lives in the `Assignment` context (it is about creating assignments in bulk).

```
app/Assignment/
├─ Domain/
│  ├─ PendingAssignmentReader.php     port (read side):
│  │     unassignedCandidatures(): list<Candidature>   — candidatures with no assignment
│  │     evaluatorLoads(): array<string,int>            — evaluatorId => current assignment count
│  └─ Exception/NoEvaluatorsAvailable.php               new (→ 409)
├─ Application/AutoAssign/
│  ├─ CandidatureAutoAssigner.php     the use case (balancing loop)
│  └─ AutoAssignmentResponse.php      DTO: { assigned, skippedIneligible }
└─ Infrastructure/
   ├─ Persistence/QueryBuilderPendingAssignmentReader.php   Query Builder impl (CQRS read)
   └─ Http/  PostCandidatureAutoAssignController
```

**Flow:**

```
POST /candidatures/auto-assign
  → PostCandidatureAutoAssignController        (thin)
  → CandidatureAutoAssigner
      loads      = reader.evaluatorLoads()             (evaluators + current counts, incl. zero)
      candidates = reader.unassignedCandidatures()     (aggregates, one query)
      eligible   = candidates filtered by CandidatureValidator.validate(...).isValid()
      if eligible not empty AND loads empty → throw NoEvaluatorsAvailable (409)
      foreach eligible:
          evaluatorId = key with the minimum load        (least-loaded)
          AssignmentRepository.save(Assignment::assign(candidatureId, evaluatorId, now))
          loads[evaluatorId]++                            (live running tally → rebalances)
  ← AutoAssignmentResponse { assigned, skipped_ineligible } → 200
```

## 2. Stack decisions

| Concern | Pick | Why |
|---|---|---|
| Selection | **unassigned ∩ eligible** | Only candidatures with no evaluator and passing the rules. |
| "unassigned" | **SQL** — `whereNotExists` against `assignments` (Query Builder) | A read; the CQRS read side. No relationship added to `CandidatureModel`. |
| "eligible" | **Domain `CandidatureValidator`** per candidature (not a SQL `years >= 2` shortcut) | The validation rules stay the single source of truth; if they change, the bulk adapts with no SQL edit. |
| Reader returns **aggregates** | `unassignedCandidatures(): list<Candidature>` | We must run the domain validator, which takes the aggregate. A deliberate, justified bend of "readers return DTOs". |
| Balancing | **In-memory running tally**; pick the min each iteration | Even distribution in a single pass; the chosen evaluator's count is bumped so the next pick rebalances. |
| No evaluators | `NoEvaluatorsAvailable` → **409 Conflict** (only when there is something to assign) | The bulk addresses no specific evaluator, so it is a state conflict, not a missing addressed resource. |
| Response | `{ assigned, skipped_ineligible }` | Observable summary of what happened. |
| Persistence | Reuse `AssignmentRepository.save` (upsert) + the `candidature_id` unique index | No new tables; single-assignment races already guarded. |
| Concurrency / scale | **Deferred to `scalability` (#7)** | Two concurrent bulk runs / very large batches (queue, idempotency key, locking) are out of scope here; a `log()`-style note: this pass is synchronous and loads all unassigned candidatures at once. |

## 3. External contract (source for `docs/openapi.yaml`)

### `POST /candidatures/auto-assign`
No request body.
- **`200 OK`**:
```json
{ "data": { "assigned": 7, "skipped_ineligible": 2 } }
```
- **`409 Conflict`** — there are eligible candidatures to assign but no evaluators exist:
```json
{ "message": "No evaluators available to assign candidatures to." }
```

When there is nothing to assign (no unassigned-eligible candidatures), the response is `200` with
`assigned: 0` — even if no evaluators exist (nothing to do is not a conflict).

## 4. Reads (CQRS)
- `unassignedCandidatures` — `SELECT * FROM candidatures WHERE NOT EXISTS (SELECT 1 FROM assignments
  WHERE assignments.candidature_id = candidatures.id)`, each row mapped to a `Candidature` aggregate
  via the existing `CandidatureMapper` (cross-context infra reuse).
- `evaluatorLoads` — `SELECT evaluators.id, COUNT(assignments.id) AS load FROM evaluators LEFT JOIN
  assignments ON assignments.evaluator_id = evaluators.id GROUP BY evaluators.id`. Includes evaluators
  with zero load. (This COUNT/GROUP BY is a warm-up for the consolidated listing #4.)

## 5. Testing approach
- **Unit** — `CandidatureAutoAssigner` with in-memory fakes + a fake reader: distributes evenly to the
  least-loaded; skips ineligible; throws `NoEvaluatorsAvailable` when eligible exist but no evaluators;
  leaves already-assigned untouched.
- **Feature/integration** (mysql-test) — `POST /candidatures/auto-assign`: assigns unassigned-eligible
  balanced across evaluators; skips ineligible and already-assigned; `409` when evaluators table empty
  but eligible candidatures exist; `200 assigned:0` when nothing to do.

## 6. Out of scope / deferred
- Concurrency of simultaneous bulk runs, queueing large batches, idempotency keys → `scalability` (#7).
- Single-candidature auto-assign → not now (bulk only).
- Alternative strategies (round-robin, random) → the least-loaded strategy is enough; a Strategy
  seam could be extracted later if more are needed.

## 7. Open questions
- None open.
