# Change: add-evaluator-assignment

## Why
Candidatures can be registered and validated, but no one owns their review. There is no way to create
evaluators or to put a candidature in the hands of one, and the upcoming consolidated listing needs
both the evaluator and the date each candidature was assigned.

- There is no way to create an evaluator.
- A candidature cannot be assigned to an evaluator, so no one is responsible for reviewing it.
- The assignment date (required by the consolidated listing) is recorded nowhere.

## What changes
- A new `POST /evaluators` endpoint creates an evaluator (name) and responds `201 Created`.
- A new `PUT /candidatures/{id}/evaluator` endpoint assigns an evaluator to a candidature and records
  the assignment date; calling it again reassigns the candidature (idempotent).
- One evaluator can be assigned to many candidatures (1:N); a candidature has at most one evaluator.
- Assigning to a missing candidature, or referencing a missing evaluator, responds `404 Not Found`.
- The candidature itself is not modified — the assignment is stored on its own.

## Impact
### Capabilities (specs)
- **NEW** `evaluator-management` — create evaluators.
- **NEW** `evaluator-assignment` — assign an evaluator to a candidature and record the date.

### External contracts
- **NEW route:** `POST /evaluators`
- **NEW route:** `PUT /candidatures/{id}/evaluator`

### Affected areas in this repo (coarse)
- **Code:** a new `Evaluator` bounded context (aggregate, repository, HTTP) and an `Assignment`
  concept (aggregate + repository) that links a candidature to an evaluator; DI wiring. The existing
  `Candidature` aggregate is untouched.
- **Database:** new `evaluators` table; new `assignments` table (`candidature_id` unique,
  `evaluator_id`, `assigned_at`). No change to `candidatures`.
- **External contract:** two new routes; no existing contract changes.

### Risk / migration
- Low: additive, greenfield tables, no data migration. Lays the foundation for the consolidated
  listing (#4) and for mass-assignment concurrency (#7) — the unique assignment per candidature is a
  race-safe idempotency guard, mirroring the email-uniqueness pattern.
