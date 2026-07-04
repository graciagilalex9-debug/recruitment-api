# Capability — evaluator-management

### Requirement: The system SHALL create an evaluator
The system SHALL accept an evaluator submitted to `POST /evaluators` with a name, persist it with a
server-generated identifier, and respond `201 Created` with the stored evaluator.

#### Scenario: A valid evaluator is created
- **WHEN** a client sends `POST /evaluators` with a non-empty name
- **THEN** the response status is `201 Created`
- **AND** the response body carries the evaluator under `data` with a server-generated `id` and the name
- **AND** the evaluator is retrievable afterwards

#### Scenario: An evaluator without a name is rejected
- **WHEN** a client sends `POST /evaluators` with a missing or empty name
- **THEN** the response status is `422 Unprocessable Entity`
- **AND** no evaluator is persisted
