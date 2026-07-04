# Capability — auto-assignment

### Requirement: The system SHALL bulk-assign unassigned, eligible candidatures to the least-loaded evaluator
The system SHALL accept `POST /candidatures/auto-assign` and assign every candidature that is both
unassigned and eligible (passes its validation rules) to the evaluator with the fewest current
assignments, rebalancing as it assigns, and respond `200 OK` with a summary of how many were assigned
and how many were skipped as ineligible. Already-assigned candidatures SHALL be left unchanged.

#### Scenario: Every unassigned eligible candidature is assigned
- **WHEN** a client calls `POST /candidatures/auto-assign` with unassigned eligible candidatures and at
  least one evaluator
- **THEN** the response status is `200 OK`
- **AND** every unassigned eligible candidature has an evaluator afterwards
- **AND** the summary reports the number assigned

#### Scenario: Assignments go to the least-loaded evaluator
- **WHEN** one evaluator already handles more candidatures than another and an unassigned eligible
  candidature is auto-assigned
- **THEN** the candidature is assigned to the less-loaded evaluator

#### Scenario: Ineligible candidatures are skipped
- **WHEN** some unassigned candidatures fail their eligibility rules
- **THEN** those candidatures receive no evaluator
- **AND** the summary counts them under the ineligible total

#### Scenario: Already-assigned candidatures are left untouched
- **WHEN** a candidature already has an evaluator
- **THEN** auto-assign does not change its evaluator

#### Scenario: Nothing to assign
- **WHEN** there are no unassigned eligible candidatures
- **THEN** the response status is `200 OK`
- **AND** the summary reports zero assigned

### Requirement: The system SHALL fail auto-assignment when there are candidatures to assign but no evaluators
The system SHALL respond `409 Conflict` to `POST /candidatures/auto-assign` when unassigned eligible
candidatures exist but no evaluator exists, and SHALL NOT record any assignment.

#### Scenario: Eligible candidatures but no evaluators
- **WHEN** a client calls `POST /candidatures/auto-assign` while unassigned eligible candidatures exist
  and no evaluator has been created
- **THEN** the response status is `409 Conflict`
- **AND** no assignment is recorded
