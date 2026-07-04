# Capability — request-idempotency

### Requirement: The system SHALL replay the original response for a retried export with the same Idempotency-Key
The system SHALL accept an optional `Idempotency-Key` header on `POST /candidatures/consolidated/export`.
The first request with a given key SHALL be processed normally and its response stored; a later request
with the same key and the same body SHALL return the stored response without creating a new report.
Requests without the header SHALL behave as before.

#### Scenario: A retry with the same key returns the original response and creates no duplicate
- **WHEN** a client sends `POST /candidatures/consolidated/export` with an `Idempotency-Key`, then
  sends the same request (same key and body) again
- **THEN** both responses are `202 Accepted` with the same report id
- **AND** only one report has been created

#### Scenario: Requests without a key are processed independently
- **WHEN** a client sends two export requests without an `Idempotency-Key`
- **THEN** two separate reports are created

### Requirement: The system SHALL reject a reused Idempotency-Key with a different payload
The system SHALL respond `422 Unprocessable Entity` when a request reuses an `Idempotency-Key` that was
already used with a different request body.

#### Scenario: Same key with a different body is rejected
- **WHEN** a client reuses an `Idempotency-Key` from a previous request but with a different body
- **THEN** the response status is `422 Unprocessable Entity`

### Requirement: The system SHALL reject a concurrent request that reuses an in-progress Idempotency-Key
The system SHALL respond `409 Conflict` when a request arrives with an `Idempotency-Key` whose original
request is still being processed.

#### Scenario: A second request with an in-progress key is rejected
- **WHEN** a client sends a request with an `Idempotency-Key` whose first request has not yet completed
- **THEN** the response status is `409 Conflict`
