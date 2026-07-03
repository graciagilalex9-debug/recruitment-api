# Change: add-candidature-validation

## Why
A candidature can be registered, but there is no way to know whether it meets the hiring criteria —
or, when it does not, which criteria it fails and why. Evaluators need to see a candidature's
eligibility, and the rule set must grow without editing the rules that already exist.

- There is no endpoint to check whether a stored candidature is eligible.
- When a candidature is not eligible, nothing reports which criteria failed and why.
- New eligibility rules cannot be added without risking changes to existing ones.

## What changes
- A new `GET /candidatures/{id}/validation` endpoint returns whether the candidature is valid and a
  per-rule breakdown (each rule: passed/failed + a human-readable reason).
- Eligibility is **computed on the fly** from the stored candidature — no persisted validation state.
- The minimum rules are: has a CV, a valid email, and at least 2 years of experience.
- The rule set is **extensible**: adding a new rule does not modify existing rules (open/closed).
- Requesting validation for a non-existent candidature returns `404 Not Found`.

## Impact
### Capabilities (specs)
- **NEW** `candidature-validation` — evaluate a stored candidature's eligibility and report why.

### External contracts
- **NEW route:** `GET /candidatures/{id}/validation` — JSON breakdown (defined in the spec deltas).

### Affected areas in this repo (coarse)
- **Code:** a new `Validation` area in the `Candidature` domain (rules + the chain that runs them),
  an application use case that loads the candidature and runs the rules, and an HTTP controller +
  route + resource. Reuses the existing `Candidature` aggregate; extends the repository with a
  lookup by id.
- **Database:** none — validation is computed, not stored.
- **External contract:** new `GET` route; no existing contract changes.

### Risk / migration
- Low: read-only, no schema change, no data migration. Establishes the Chain-of-Responsibility
  pattern that future optional rules plug into without touching existing ones.
