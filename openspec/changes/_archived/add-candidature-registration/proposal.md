# Change: add-candidature-registration

## Why
The API cannot record job candidatures yet — there is no endpoint to submit one and no place to store
one. This is the foundational capability every other feature builds on (validation, evaluator
assignment, listings, reports all operate on stored candidatures).

- There is no way for a client to register a candidature.
- Candidature data (full name, email, years of experience, CV, creation date) is not persisted anywhere.
- Without a stored candidature there is nothing for the later capabilities to reference.

## What changes
- A new `POST /candidatures` endpoint accepts a candidature and persists it.
- The request is validated at the HTTP boundary (required fields, well-formed email, non-negative
  years of experience) before it reaches the domain.
- On success the API responds `201 Created` with the stored candidature (including its generated id
  and creation timestamp); on invalid input it responds `422` with per-field errors.
- The candidature is stored so later capabilities can read and act on it.

## Impact
### Capabilities (specs)
- **NEW** `candidature-registration` — register and persist a candidature via the API.

### External contracts
- **NEW route:** `POST /candidatures` — JSON request/response contract (defined in the spec deltas).

### Affected areas in this repo (coarse)
- **Code:** new `Candidature` bounded context across all three layers (Domain aggregate + value
  objects, Application registrar use case, Infrastructure HTTP + Eloquent persistence + DI wiring).
- **Database:** new `candidatures` table (migration ships with this change).
- **External contract:** new `POST /candidatures` route; no existing contract changes.

### Risk / migration
- Greenfield capability — no existing data or callers to migrate, low risk.
- Establishes the hexagonal layering template (domain-aggregate ↔ Eloquent-model mapper) that the
  remaining capabilities will follow, so the shape chosen here matters beyond this change.
