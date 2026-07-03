# Capability — candidature-validation

### Requirement: The system SHALL report a candidature's eligibility with a per-rule breakdown
The system SHALL evaluate a stored candidature against its eligibility rules and respond to
`GET /candidatures/{id}/validation` with `200 OK` and a breakdown that, for each rule, states whether
it passed and a human-readable reason, together with an overall `valid` flag that is true only when
every rule passed.

#### Scenario: An eligible candidature passes every rule
- **WHEN** a client requests `GET /candidatures/{id}/validation` for a stored candidature that meets
  every eligibility rule
- **THEN** the response status is `200 OK`
- **AND** the body reports `valid` as `true`
- **AND** every rule in the breakdown is reported as passed

#### Scenario: An ineligible candidature reports which rule failed and why
- **WHEN** a client requests `GET /candidatures/{id}/validation` for a stored candidature with fewer
  than two years of experience
- **THEN** the response status is `200 OK`
- **AND** the body reports `valid` as `false`
- **AND** the minimum-experience rule is reported as failed with a reason
- **AND** the rules the candidature satisfies are reported as passed

### Requirement: The system SHALL return not found when validating a missing candidature
The system SHALL respond to `GET /candidatures/{id}/validation` for an id that does not belong to any
candidature with `404 Not Found`.

#### Scenario: Validating a non-existent candidature
- **WHEN** a client requests `GET /candidatures/{id}/validation` with an id that matches no candidature
- **THEN** the response status is `404 Not Found`
