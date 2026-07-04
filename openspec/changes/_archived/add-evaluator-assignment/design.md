# Design — add-evaluator-assignment

Introduces an `Evaluator` context and an `Assignment` concept linking a candidature to an evaluator.
The `Candidature` aggregate is untouched (stays immutable); the assignment lives in its own table.

## 1. Architecture

```
app/Evaluator/                       (new bounded context)
├─ Domain/
│  ├─ Evaluator.php                   aggregate (id, name)
│  ├─ ValueObject/EvaluatorId.php     ULID
│  ├─ ValueObject/EvaluatorName.php   non-empty
│  ├─ EvaluatorRepository.php         port: nextIdentity, findById, save
│  └─ Exception/{InvalidEvaluatorName, EvaluatorNotFound}.php
├─ Application/Register/  RegisterEvaluatorCommand · EvaluatorCreator · EvaluatorResponse
└─ Infrastructure/
   ├─ Http/  PostEvaluatorController · RegisterEvaluatorRequest · EvaluatorResource
   ├─ Persistence/  EvaluatorModel · EvaluatorMapper · EloquentEvaluatorRepository
   └─ Providers/  EvaluatorServiceProvider

app/Assignment/                      (new context: links Candidature <-> Evaluator)
├─ Domain/
│  ├─ Assignment.php                  aggregate (candidatureId, evaluatorId, assignedAt)
│  └─ AssignmentRepository.php        port: save (upsert by candidature), findByCandidature
├─ Application/Assign/  EvaluatorAssigner · AssignmentResponse
└─ Infrastructure/
   ├─ Http/  PutCandidatureEvaluatorController · AssignEvaluatorRequest · AssignmentResource
   ├─ Persistence/  AssignmentModel · AssignmentMapper · EloquentAssignmentRepository
   └─ Providers/  AssignmentServiceProvider
```

**Assign flow:**

```
PUT /candidatures/{id}/evaluator  { "evaluator_id": "..." }
  → PutCandidatureEvaluatorController        (thin)
  → EvaluatorAssigner
      · CandidatureRepository.findById  → 404 CandidatureNotFound if absent
      · EvaluatorRepository.findById    → 404 EvaluatorNotFound if absent
      · CandidatureValidator.validate   → 409 CandidatureNotEligible if not valid  (reuses #2)
      · Assignment::assign(candidatureId, evaluatorId, now) ; AssignmentRepository.save (upsert)
  ← AssignmentResponse DTO                    → 200
```

The assigner orchestrates three ports across contexts (Candidature, Evaluator, Assignment) — legit
at the application layer. `now` is supplied at the application boundary (clock-free domain).

## 2. Stack decisions

| Concern | Pick | Why |
|---|---|---|
| Assignment storage | **Separate `assignments` table** (`candidature_id` unique) | Candidature stays immutable; history-friendly; the unique key is a race-safe idempotency guard (mirrors email uniqueness). |
| Reassignment | **Upsert by `candidature_id`** (`updateOrCreate`) | One current evaluator per candidature; calling `PUT` again just updates the row → idempotent. |
| Endpoint (assign) | **`PUT /candidatures/{id}/evaluator`** | Reads as "set this candidature's evaluator"; idempotent; hides storage. |
| Endpoint (create) | **`POST /evaluators`** | Self-contained: create evaluators via the API, not only seeders. |
| Candidature missing | `CandidatureNotFound` → **404** (reuse) | The URL resource. Route `->whereUlid('id')` → malformed id is a 404 route miss. |
| Evaluator missing | `EvaluatorNotFound` → **404** | Well-formed but unknown evaluator id (body). |
| Eligibility gate | Reuse `CandidatureValidator` (#2); ineligible → **409** `CandidatureNotEligible` | **Our own decision — NOT in the brief.** Only eligible candidatures should reach an evaluator; it adds realism and shows capabilities #2 and #3 composing. Computed on the fly (no states). |
| Malformed `evaluator_id` | FormRequest `ulid` rule → **422** | Input-shape check at the boundary (format), distinct from the not-found business check. |
| Identity | ULID (`EvaluatorId`), generated via the repo port | Same rationale as `CandidatureId`. (The ULID VO logic is duplicated for now; a shared `Ulid` base is a possible later refactor.) |
| History | **Deferred** (current assignment only) | PDF marks it optional; the separate table makes adding it trivial later. |

## 3. External contract (source for `docs/openapi.yaml`)

### `POST /evaluators`
Request: `{ "name": "Grace Hopper" }` (name required, string, 1–255).
- **`201 Created`**:
```json
{ "data": { "id": "01J...ULID", "name": "Grace Hopper", "created_at": "2026-07-04T10:00:00+00:00" } }
```
- **`422`** — malformed/missing name (Laravel validation shape).

### `PUT /candidatures/{id}/evaluator`
Request: `{ "evaluator_id": "01J...ULID" }` (required, `ulid`).
- **`200 OK`**:
```json
{ "data": { "candidature_id": "01J...", "evaluator_id": "01J...", "assigned_at": "2026-07-04T10:05:00+00:00" } }
```
- **`404`** — candidature not found, or evaluator not found.
- **`409`** — the candidature is not eligible for assignment (fails its validation rules).
- **`422`** — missing / non-ULID `evaluator_id`.

## 4. Persistence

`evaluators` — `id char(26)` PK, `name varchar(255)`, `created_at`.

`assignments`:

| Column | Type | Notes |
|---|---|---|
| `id` | `char(26)` | PK (ULID) |
| `candidature_id` | `char(26)` | **unique**; FK → candidatures (covering index from the unique) |
| `evaluator_id` | `char(26)` | FK → evaluators (**indexed** — the listing groups by it) |
| `assigned_at` | `timestamp` | domain-owned |

The `candidature_id` unique index guarantees one current assignment per candidature and is the
concurrency guard for mass assignment (#7).

## 5. Testing approach
- **Unit** — `EvaluatorName` (rejects empty); `EvaluatorAssigner` with in-memory fakes (assigns;
  reassign updates; throws on missing candidature / evaluator).
- **Feature/integration** (mysql-test) — `POST /evaluators` → 201 + row; `PUT …/evaluator` → 200 +
  assignment row; reassigning the same candidature keeps one row (updated); 404 for missing
  candidature and missing evaluator; 422 for a non-ULID `evaluator_id`.

## 6. Out of scope / deferred
- **History / audit trail (bitácora)** for candidatures *and* assignments → dedicated next capability
  `add-activity-log`, fed by **domain events** (registered / validated / assigned / reassigned). This
  is where the event hook left in `Candidature::register()` finally pays off. Assignments here use
  upsert (current evaluator only); reassignment history will come from that event log, not from
  appending assignment rows.
- Candidature **states** (persisted lifecycle) → optional later capability; deliberately avoided here
  (the eligibility gate is computed on the fly, so no `status` column / mutability is needed).
- Listing candidatures with evaluators, per-evaluator counts, concatenated emails → `consolidated-listing` (#4).
- Mass-assignment concurrency hardening → `scalability` (#7); the unique key already makes single
  assignments race-safe.
- Shared `Ulid` value-object base (dedupe `CandidatureId`/`EvaluatorId`) → optional refactor.

## 7. Open questions
- None open.
