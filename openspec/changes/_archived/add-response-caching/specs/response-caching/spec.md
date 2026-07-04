# Spec delta — response-caching

## ADDED Requirements

### Requirement: The system SHALL serve the consolidated listing from a cache while keeping it consistent with the assignments
The system SHALL cache the consolidated listing and MAY serve repeated identical requests from that
cache, but a listing request SHALL always reflect every assignment created before it (the cache is
invalidated when assignments change). The response body SHALL be identical to the uncached listing.

#### Scenario: The listing reflects a newly created assignment
- **WHEN** a client requests `GET /candidatures/consolidated`, then a new assignment is created, then
  the client requests the listing again
- **THEN** the second response includes the newly assigned candidature (and the evaluator's updated
  total)

#### Scenario: Repeated identical listing requests return the same data
- **WHEN** a client requests the same `GET /candidatures/consolidated` (same sort/filter/page) twice
  with no intervening writes
- **THEN** both responses return the same rows and totals

### Requirement: The system SHALL serve the candidature validation report from a cache
The system SHALL cache the candidature eligibility report and MAY serve repeated requests for the same
candidature from that cache, returning the same report each time (safe because a candidature is
immutable). The response body SHALL be identical to the uncached report.

#### Scenario: Repeated validation requests return the same report
- **WHEN** a client requests `GET /candidatures/{id}/validation` for the same candidature more than
  once
- **THEN** every response returns the same eligibility report
