# Spec delta — evaluator-assignment

## ADDED Requirements

### Requirement: The system SHALL assign an evaluator to a candidature and record the date
The system SHALL accept `PUT /candidatures/{id}/evaluator` with an evaluator id, assign that evaluator
to the candidature, record the assignment date, and respond `200 OK`. An evaluator MAY be assigned to
many candidatures, and a candidature SHALL have at most one evaluator — reassigning replaces it.

#### Scenario: An evaluator is assigned to a candidature
- **WHEN** a client sends `PUT /candidatures/{id}/evaluator` with the id of an existing evaluator, for
  an existing candidature
- **THEN** the response status is `200 OK`
- **AND** the response body reports the candidature, the assigned evaluator, and the assignment date
- **AND** the assignment is retrievable afterwards

#### Scenario: One evaluator handles multiple candidatures
- **WHEN** the same evaluator is assigned to two different candidatures
- **THEN** both assignments succeed with `200 OK`

#### Scenario: Reassigning replaces the candidature's evaluator
- **WHEN** a candidature that already has an evaluator is assigned a different evaluator
- **THEN** the response status is `200 OK`
- **AND** the candidature has exactly one evaluator afterwards — the new one

### Requirement: The system SHALL reject assignment when the candidature or evaluator does not exist
The system SHALL respond `404 Not Found` to `PUT /candidatures/{id}/evaluator` when the candidature
does not exist or the referenced evaluator does not exist, and SHALL NOT record an assignment.

#### Scenario: The candidature does not exist
- **WHEN** a client assigns an evaluator to a candidature id that matches no candidature
- **THEN** the response status is `404 Not Found`
- **AND** no assignment is recorded

#### Scenario: The evaluator does not exist
- **WHEN** a client assigns a non-existent evaluator to an existing candidature
- **THEN** the response status is `404 Not Found`
- **AND** no assignment is recorded

### Requirement: The system SHALL only assign an evaluator to an eligible candidature
The system SHALL evaluate the candidature against its eligibility rules before assigning, and SHALL
respond `409 Conflict` without recording an assignment when the candidature is not eligible.

#### Scenario: An ineligible candidature cannot be assigned
- **WHEN** a client assigns an evaluator to an existing candidature that fails its eligibility rules
  (e.g. fewer than two years of experience)
- **THEN** the response status is `409 Conflict`
- **AND** no assignment is recorded

### Requirement: The system SHALL reject a malformed assignment request
The system SHALL respond `422 Unprocessable Entity` to `PUT /candidatures/{id}/evaluator` when the
`evaluator_id` is missing or not a well-formed identifier, without recording an assignment.

#### Scenario: The evaluator id is missing or malformed
- **WHEN** a client sends `PUT /candidatures/{id}/evaluator` without an `evaluator_id`, or with one
  that is not a well-formed ULID
- **THEN** the response status is `422 Unprocessable Entity`
- **AND** no assignment is recorded
