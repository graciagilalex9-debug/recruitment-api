# Spec delta — excel-report

## ADDED Requirements

### Requirement: The system SHALL accept an asynchronous export request for the consolidated listing
The system SHALL respond to `POST /candidatures/consolidated/export` with `202 Accepted`, creating a
report in `pending` status and returning its id, without building the file during the request. The
request SHALL accept the same sort and filter parameters as the consolidated listing.

#### Scenario: An export request is accepted
- **WHEN** a client sends `POST /candidatures/consolidated/export` with valid parameters
- **THEN** the response status is `202 Accepted`
- **AND** the body contains a report id and a status of `pending`

#### Scenario: An invalid export request is rejected
- **WHEN** a client requests an export ordered by a column that is not part of the listing (or an
  invalid direction)
- **THEN** the response status is `422 Unprocessable Entity`

### Requirement: The system SHALL generate the export asynchronously and mark the report completed
The system SHALL build the workbook from the consolidated listing in the background, honouring the
requested filters and sort, splitting the candidatures into sheets of at most 50 rows each, and SHALL
mark the report `completed` when the file is ready.

#### Scenario: A requested export completes and becomes downloadable
- **WHEN** an export has been requested and the background generation has run
- **THEN** the report status becomes `completed`
- **AND** the report exposes a download link for the generated file

#### Scenario: Candidatures are split into sheets of at most 50
- **WHEN** an export is generated for more than 50 assigned candidatures
- **THEN** the generated workbook splits the candidatures across sheets of at most 50 rows each

#### Scenario: The export honours the requested filter
- **WHEN** an export is requested with a filter on a listed column
- **THEN** the generated workbook contains only the candidatures matching that filter

### Requirement: The system SHALL expose the status of a report
The system SHALL respond to `GET /reports/{id}` with `200 OK` and the report's type, status and
timestamps (and a download link once completed), and SHALL respond `404 Not Found` when no report has
that id.

#### Scenario: The status of an existing report is returned
- **WHEN** a client requests `GET /reports/{id}` for an existing report
- **THEN** the response status is `200 OK`
- **AND** the body reports the current status of the report

#### Scenario: The status of an unknown report is not found
- **WHEN** a client requests `GET /reports/{id}` for an id that matches no report
- **THEN** the response status is `404 Not Found`

### Requirement: The system SHALL allow downloading a completed report
The system SHALL respond to `GET /reports/{id}/download` with `200 OK` and the generated `.xlsx` file
when the report is `completed`, SHALL respond `409 Conflict` when the report exists but is not yet
completed, and SHALL respond `404 Not Found` when no report has that id.

#### Scenario: A completed report is downloaded
- **WHEN** a client requests `GET /reports/{id}/download` for a completed report
- **THEN** the response status is `200 OK`
- **AND** the response body is the generated spreadsheet with a spreadsheet content type

#### Scenario: Downloading a report that is not ready is refused
- **WHEN** a client requests the download of a report that is not yet completed
- **THEN** the response status is `409 Conflict`

#### Scenario: Downloading an unknown report is not found
- **WHEN** a client requests the download of an id that matches no report
- **THEN** the response status is `404 Not Found`

### Requirement: The system SHALL notify the requester by email when the export completes
The system SHALL send an email containing the download link when a report reaches the `completed`
status.

#### Scenario: An email is sent on completion
- **WHEN** a report's background generation completes
- **THEN** an email containing the download link is sent

### Requirement: The system SHALL record a failed export with its reason
The system SHALL mark a report `failed` and record the failure reason when its background generation
cannot complete.

#### Scenario: A failed generation is reflected in the report status
- **WHEN** the background generation of a report fails
- **THEN** the report status becomes `failed`
- **AND** the report exposes the failure reason
