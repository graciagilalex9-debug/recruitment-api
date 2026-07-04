# Design — add-candidature-summary

A read that composes what other capabilities already produce. Almost entirely pattern-following; the
one decision worth recording is **where Collections live**.

## 1. Architecture

```
app/Candidature/
├─ Application/Summary/
│  ├─ CandidatureSummary.php        DTO (candidature data + valid + rule results + evaluator?)
│  └─ CandidatureSummaryFinder.php  use case: load + validate + look up evaluator → DTO
└─ Infrastructure/Http/
   ├─ GetCandidatureSummaryController.php
   └─ CandidatureSummaryResource.php  shapes JSON; uses Collections for the passed/failed split
```

**Flow:**

```
GET /candidatures/{id}/summary
  → GetCandidatureSummaryController
  → CandidatureSummaryFinder
      · CandidatureRepository.findById  → 404 CandidatureNotFound if absent
      · CandidatureValidator.validate   (reuse #2)
      · AssignmentRepository.findByCandidature → EvaluatorRepository.findById  (reuse #3, optional)
  ← CandidatureSummary DTO           → 200 (shaped by the resource)
```

## 2. Stack decisions

| Concern | Pick | Why |
|---|---|---|
| Composition | Reuse `CandidatureValidator` (#2) + assignment/evaluator lookups (#3) | The summary is a *view* over existing behaviour; no new logic. |
| Not found | `CandidatureNotFound` → **404** (already mapped) | Consistent with the other candidature reads. |
| **Where Collections live** | In the **HTTP resource (Infrastructure)** — split passed/failed, shape JSON | The PDF suggests Collections for the summary "processing"; `Illuminate\Support\Collection` is a Laravel utility, so it belongs in Infrastructure. The Application `CandidatureSummary` DTO stays pure (plain arrays), preserving `Application → Domain only`. |
| Evaluator | `{ name, assigned_at }` or `null` | The candidature may be unassigned. |

## 3. External contract (source for `docs/openapi.yaml`)

### `GET /candidatures/{id}/summary`
- **`200 OK`**:
```json
{
  "data": {
    "id": "01J...", "full_name": "Ada Lovelace", "email": "ada@example.com",
    "years_of_experience": 9, "cv": "…", "created_at": "2026-07-04T10:00:00+00:00",
    "valid": true,
    "validations": {
      "passed": [ { "rule": "has_cv", "reason": "…" }, { "rule": "valid_email", "reason": "…" } ],
      "failed": [ ]
    },
    "evaluator": { "name": "Grace Hopper", "assigned_at": "2026-07-04T10:05:00+00:00" }
  }
}
```
`evaluator` is `null` when the candidature has no evaluator.
- **`404`** — no candidature with that id.

## 4. Testing approach
- **Feature/integration** (mysql-test): the summary of a valid, assigned candidature shows its data,
  all rules under `passed`, and the evaluator; the summary of an ineligible, unassigned candidature
  shows the failing rule under `failed` and `evaluator: null`; an unknown id → `404`.

## 5. Out of scope
- Candidature states → still deferred (optional capability).

## 6. Open questions
- None.
