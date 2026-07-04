# Spec delta — consolidated-listing

## ADDED Requirements

### Requirement: The system SHALL return assigned candidatures enriched with evaluator context
The system SHALL respond to `GET /candidatures/consolidated` with `200 OK` and, for every candidature
that has an evaluator assigned, a row containing the candidate's full name, email and years of
experience, the evaluator's name, the assignment date, the total number of candidatures assigned to
that evaluator, and a concatenated list of the emails of all candidates that evaluator handles.

#### Scenario: Assigned candidatures are returned with evaluator context
- **WHEN** a client requests `GET /candidatures/consolidated` and assigned candidatures exist
- **THEN** the response status is `200 OK`
- **AND** each row includes the candidate full name, email, years of experience, evaluator name and
  assignment date
- **AND** each row includes the evaluator's total assigned candidatures and the concatenated emails of
  that evaluator's candidates

#### Scenario: Candidatures without an evaluator are excluded
- **WHEN** some candidatures have no evaluator assigned
- **THEN** those candidatures do not appear in the listing

### Requirement: The system SHALL order the listing by any listed column, defaulting to years of experience descending
The system SHALL order the listing by a requested listed column and direction, and SHALL default to
years of experience in descending order when no order is requested.

#### Scenario: Default order is years of experience descending
- **WHEN** a client requests `GET /candidatures/consolidated` without an order
- **THEN** the rows are ordered by years of experience, highest first

#### Scenario: Ordering by a chosen column and direction
- **WHEN** a client requests the listing sorted by a listed column in a given direction (e.g.
  `evaluator_name` ascending)
- **THEN** the rows are ordered by that column in that direction

### Requirement: The system SHALL filter the listing by a listed column
The system SHALL narrow the listing to the rows matching a filter on a listed column.

#### Scenario: Filtering narrows the results
- **WHEN** a client requests the listing filtered by a listed column value
- **THEN** only the rows matching that filter are returned

### Requirement: The system SHALL paginate the listing
The system SHALL return the listing in pages, reporting the current page, page size and total count.

#### Scenario: The listing is paginated
- **WHEN** a client requests the listing with a page size
- **THEN** at most that many rows are returned
- **AND** the response reports the current page, the page size and the total number of rows

### Requirement: The system SHALL reject an invalid listing request
The system SHALL respond `422 Unprocessable Entity` when the request asks to order by a column that is
not part of the listing (or an invalid direction / pagination value).

#### Scenario: Ordering by an unknown column is rejected
- **WHEN** a client requests the listing sorted by a column that is not part of the listing
- **THEN** the response status is `422 Unprocessable Entity`
