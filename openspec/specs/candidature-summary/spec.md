# Capability — candidature-summary

### Requirement: The system SHALL return a consolidated summary of a candidature
The system SHALL respond to `GET /candidatures/{id}/summary` with `200 OK` and the candidature's full
data, an overall validity flag, the breakdown of which eligibility rules passed and which failed (with
reasons), and the assigned evaluator (name and assignment date) when one exists.

#### Scenario: Summary of a valid, assigned candidature
- **WHEN** a client requests `GET /candidatures/{id}/summary` for a candidature that meets every rule
  and has an evaluator assigned
- **THEN** the response status is `200 OK`
- **AND** the body includes the candidature's full data and `valid` is true
- **AND** every rule appears under the passed breakdown and none under the failed breakdown
- **AND** the assigned evaluator's name and assignment date are included

#### Scenario: Summary of an ineligible, unassigned candidature
- **WHEN** a client requests the summary for a candidature that fails a rule and has no evaluator
- **THEN** the response status is `200 OK`
- **AND** `valid` is false and the failing rule appears under the failed breakdown with a reason
- **AND** the evaluator is reported as null

### Requirement: The system SHALL return not found for a summary of a missing candidature
The system SHALL respond `404 Not Found` to `GET /candidatures/{id}/summary` when no candidature has
that id.

#### Scenario: Summary of a non-existent candidature
- **WHEN** a client requests the summary for an id that matches no candidature
- **THEN** the response status is `404 Not Found`
