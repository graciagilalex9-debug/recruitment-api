# Capability — candidature-registration

### Requirement: The system SHALL register and persist a valid candidature
The system SHALL accept a candidature submitted to `POST /candidatures` with a full name, email,
years of experience and CV, persist it with a server-generated identifier and creation timestamp, and
respond `201 Created` with the stored candidature.

#### Scenario: A valid candidature is registered
- **WHEN** a client sends `POST /candidatures` with a valid full name, email, non-negative years of
  experience and non-empty CV
- **THEN** the response status is `201 Created`
- **AND** the response body carries the candidature under `data` with a server-generated `id` and a
  `created_at` timestamp
- **AND** the candidature is retrievable from storage afterwards

#### Scenario: The stored email is normalized to lowercase
- **WHEN** a client registers a candidature with an email containing uppercase letters (e.g.
  `Ada@Example.COM`)
- **THEN** the response status is `201 Created`
- **AND** the persisted and returned email is normalized to lowercase (`ada@example.com`)

### Requirement: The system SHALL reject a candidature whose email is already registered
The system SHALL treat the email as the business identity of a candidature: it SHALL reject a
`POST /candidatures` request whose email already belongs to a registered candidature with
`409 Conflict`, SHALL NOT create a second candidature, and SHALL compare emails case-insensitively.

#### Scenario: The email already exists
- **WHEN** a client sends `POST /candidatures` with an email that already belongs to a registered
  candidature
- **THEN** the response status is `409 Conflict`
- **AND** no second candidature is persisted for that email

#### Scenario: The email already exists differing only in case
- **WHEN** a client registers an email that matches an existing one except for letter case (e.g.
  `ADA@example.com` when `ada@example.com` exists)
- **THEN** the response status is `409 Conflict`
- **AND** no second candidature is persisted for that email

### Requirement: The system SHALL reject a malformed candidature without persisting it
The system SHALL reject a `POST /candidatures` request whose input is missing required fields or
malformed with `422 Unprocessable Entity` and per-field error messages, and SHALL NOT persist any
candidature.

#### Scenario: A required field is missing
- **WHEN** a client sends `POST /candidatures` omitting a required field (e.g. `email`)
- **THEN** the response status is `422 Unprocessable Entity`
- **AND** the response body reports an error for the missing field
- **AND** no candidature is persisted

#### Scenario: The email is malformed
- **WHEN** a client sends `POST /candidatures` with an email that is not a valid address
- **THEN** the response status is `422 Unprocessable Entity`
- **AND** the response body reports an error for the `email` field
- **AND** no candidature is persisted

#### Scenario: Years of experience is negative
- **WHEN** a client sends `POST /candidatures` with a negative `years_of_experience`
- **THEN** the response status is `422 Unprocessable Entity`
- **AND** the response body reports an error for the `years_of_experience` field
- **AND** no candidature is persisted
