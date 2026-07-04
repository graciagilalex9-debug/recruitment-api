# Change: add-candidature-summary

## Why
There is no single place to see everything about a candidature at once — its data, whether it passes
each eligibility rule, and who (if anyone) is evaluating it. Reviewers have to call several endpoints
and stitch the picture together.

- No endpoint returns a candidature's full data + validation breakdown + evaluator in one response.
- The passed/failed validation split is not exposed for a single candidature.
- Whether a candidature is assigned (and to whom, since when) is not visible alongside its data.

## What changes
- A new `GET /candidatures/{id}/summary` endpoint returns the candidature's full data, its validation
  breakdown (which rules passed and which failed, with reasons), an overall `valid` flag, and its
  evaluator (name + assignment date) when one is assigned (`null` otherwise).
- Requesting the summary of a non-existent candidature returns `404 Not Found`.

## Impact
### Capabilities (specs)
- **NEW** `candidature-summary` — one consolidated view of a single candidature.

### External contracts
- **NEW route:** `GET /candidatures/{id}/summary`

### Affected areas in this repo (coarse)
- **Code:** a read use case that reuses the candidature repository, the validator (#2) and the
  assignment/evaluator lookups (#3); an HTTP controller + resource + route. No new aggregates, no
  writes, no schema changes. Collections are used in the HTTP resource to shape the passed/failed split.
- **Database:** none.
- **External contract:** one new route.

### Risk / migration
- Low: read-only, additive, reuses existing behaviour end to end.
