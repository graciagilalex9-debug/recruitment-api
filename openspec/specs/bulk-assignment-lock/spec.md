# Capability — bulk-assignment-lock

### Requirement: The system SHALL run at most one bulk auto-assignment at a time
The system SHALL execute `POST /candidatures/auto-assign` under an exclusive lock so only one bulk run
proceeds at a time. A request that arrives while another run is in progress SHALL be rejected with
`409 Conflict` without processing. The lock SHALL be released when the run finishes, so later requests
proceed normally.

#### Scenario: A concurrent auto-assignment is rejected
- **WHEN** a client sends `POST /candidatures/auto-assign` while another auto-assignment is already in
  progress
- **THEN** the response status is `409 Conflict`
- **AND** the second request does not assign any candidatures

#### Scenario: Auto-assignment works again once the previous run has finished
- **WHEN** a client sends `POST /candidatures/auto-assign`, it completes, and the client sends another
  `POST /candidatures/auto-assign`
- **THEN** both responses are successful (the lock is released after each run)
