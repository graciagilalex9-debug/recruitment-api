# Design — add-candidature-validation

Adds an endpoint that reports a stored candidature's eligibility and *why*, built on an extensible
rule set. Reuses the `Candidature` aggregate from `candidature-registration`.

## 1. Architecture (the validation slice)

```
app/Candidature/
├─ Domain/
│  ├─ CandidatureRepository.php          + findById(CandidatureId): ?Candidature   (extend the port)
│  ├─ Exception/CandidatureNotFound.php  new
│  └─ Validation/
│      ├─ ValidationRule.php             interface: evaluate(Candidature): RuleResult
│      ├─ RuleResult.php                 VO: key, passed (bool), reason (string)
│      ├─ ValidationReport.php           VO: RuleResult[]; isValid() = all passed
│      ├─ CandidatureValidator.php       runs an ordered iterable<ValidationRule>, collects → report
│      └─ Rule/
│          ├─ MustHaveCv.php
│          ├─ MustHaveValidEmail.php
│          └─ MustHaveMinimumExperience.php     (>= 2 years)
├─ Application/
│  └─ Validate/
│      ├─ CandidatureValidationFinder.php    load by id (or throw NotFound) → run validator → DTO
│      └─ ValidationReportResponse.php        output DTO (primitives)
└─ Infrastructure/
   ├─ Http/
   │   ├─ GetCandidatureValidationController.php
   │   └─ ValidationReportResource.php
   ├─ Persistence/  EloquentCandidatureRepository::findById (reuses the existing mapper.toDomain)
   └─ Providers/    register the rule list + CandidatureValidator in the container
```

**Request flow:**

```
GET /candidatures/{id}/validation
  → GetCandidatureValidationController          (thin)
  → CandidatureValidationFinder                 (repo.findById → 404 if null)
  → CandidatureValidator.validate(candidature)  (runs every rule, collects RuleResults)
  ← ValidationReportResponse DTO                → 200 JSON  (or 404 if not found)
```

## 2. Stack decisions

| Concern | Pick | Why |
|---|---|---|
| **Validation pattern** | **Rule-collection pipeline** (ordered `iterable<ValidationRule>` run by `CandidatureValidator`) — chosen over a classic linked Chain of Responsibility | See §3. Stateless, cacheable, flexible for future state-dependent rule sets, marginally cheaper; still open/closed. |
| Extensibility | `ValidationRule` interface + the ordered rule list assembled in the provider | Add a rule = new class + one line in the provider. Existing rules and the validator are never modified (open/closed). |
| Rules run | **All rules, always** (collect every result) | The endpoint must report *why* — every passed/failed criterion, not just the first failure. |
| Loading the candidature | Extend the port with `findById(CandidatureId): ?Candidature`, returning the **aggregate** (reuse `CandidatureMapper::toDomain`) | Rules are domain logic → they need the domain aggregate, not a DTO. |
| Not found | `CandidatureNotFound` domain exception → `404` (mapped in `bootstrap/app.php`) | Consistent with how `CandidatureAlreadyExists` → `409` is handled. |
| Result handling | **Computed on the fly, not persisted** | The PDF does not require persistence; candidatures are immutable, so the report is stable and cacheable later (see §6). |
| Serialization | `JsonResource` (`ValidationReportResource`), snake_case, wrapped in `data` | Consistent with the registration slice. |

## 3. Why a rule-collection pipeline instead of classic Chain of Responsibility

The PDF suggests Chain of Responsibility ("favorece… sin imponer"). We implement the same intent — an
**extensible rule set where adding a rule never modifies existing rules** — but as an ordered
collection of rules run by a validator, rather than a linked list of handlers each holding a `next`.
Both are open/closed; we pick the pipeline deliberately:

- **Stateless singleton.** The validator holds no per-request state, so it is registered once in the
  container and reused across all requests with zero per-request allocation. A linked chain must be
  assembled (or carefully shared) and is more awkward to reuse safely.
- **Scale / caching.** Because candidatures are immutable (insert-only), a candidature's report is
  stable and can be wrapped in `Cache::remember(...)`. A stateless validator returning a pure report
  composes cleanly with that; the report is the real high-traffic lever, not the per-rule cost
  (which is trivial in-memory work, dwarfed by the `findById` read).
- **Efficiency.** A flat iteration avoids the recursive `next?->handle()` call chain. Negligible in
  absolute terms, but never worse.
- **Future-proofing for states.** If candidature *states* are added later and different rules must
  apply per state, filtering/selecting a **collection** by state is natural; a fixed linked chain is
  rigid (rebuild chains or add conditional pass-through in every handler).

Classic CoR shines when exactly one handler should *consume* a request and short-circuit. Here we
want the opposite — run everything and aggregate — so the collection is the better fit. This is a
conscious variant choice, not an omission of the pattern.

## 4. External contract (source for `docs/openapi.yaml`)

### `GET /candidatures/{id}/validation`

- **`200 OK`** — the eligibility report:
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
- **`404 Not Found`** — no candidature with that id:
```json
{ "message": "Candidature 01J9Z8K3Q7R5X2M4B6T8V0W1C2 not found." }
```

`valid` is `true` only when every rule passed.

## 5. The rules

| Rule key | Passes when | Note |
|---|---|---|
| `has_cv` | the CV is non-empty | Registration already guarantees this; kept per the PDF and to demonstrate the pattern. |
| `valid_email` | the email is well-formed | Same — the `Email` VO already guarantees it for stored candidatures. |
| `minimum_experience` | `years_of_experience >= 2` | The rule that actually differentiates eligibility today. |

> Because registration already enforces a valid email and a non-empty CV, for a *stored* candidature
> those two rules effectively always pass; `minimum_experience` is the meaningful one. All three are
> implemented anyway — the PDF lists them and they exercise the extensible rule set. Adding an
> optional rule later (e.g. a disposable-email check) is a new class + one provider line.

## 6. Scale hook (future)
`CandidatureValidationFinder` can wrap the report in `Cache::remember("candidature-validation:{id}", …)`.
Safe today because candidatures are immutable; if states are introduced (candidatures become mutable),
the cache key must be invalidated on change. Deferred to the `scalability` capability.

## 7. Testing approach
- **Unit** — each rule (`MustHaveMinimumExperience` passes at 2, fails at 1; etc.) and
  `CandidatureValidator` (aggregates results, `isValid()` only when all pass).
- **Feature/integration** — `GET /candidatures/{id}/validation` against `mysql-test`: `200` with the
  breakdown for a valid and an invalid candidature; `404` for an unknown id. No internal mocks.

## 8. Out of scope / deferred
- Persisting validation results, candidature states, activity log → future capabilities.
- Caching the report → `scalability` capability (hook noted in §6).
- Candidate-defined *optional* rules beyond the three minimums → trivial to add later (new class + one
  provider line); not needed to prove the design.
