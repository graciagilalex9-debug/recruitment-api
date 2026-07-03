# Design — add-candidature-registration

This change establishes the **hexagonal template** the whole codebase follows. Later capabilities copy
this shape, so the decisions here are repo-wide, not local.

## 1. Architecture (the vertical slice)

One bounded context, `Candidature`, split across the three layers. Dependencies point inward only.

```
app/Candidature/
├─ Domain/                      (pure PHP — imports nothing from Laravel/Eloquent)
│  ├─ Candidature.php               aggregate root
│  ├─ ValueObject/
│  │   ├─ CandidatureId.php         ULID identity (time-sortable)
│  │   ├─ FullName.php              non-empty
│  │   ├─ Email.php                 well-formed, normalized lowercase
│  │   ├─ YearsOfExperience.php     integer >= 0
│  │   └─ Cv.php                    non-empty text
│  ├─ CandidatureRepository.php     interface (save, nextIdentity, existsByEmail)
│  └─ Exception/                    domain exceptions (InvalidEmail, CandidatureAlreadyExists)
├─ Application/
│  └─ Register/
│      ├─ RegisterCandidatureCommand.php   input DTO (primitives)
│      ├─ CandidatureRegistrar.php         use case
│      └─ CandidatureResponse.php          output DTO (primitives)
└─ Infrastructure/
   ├─ Http/
   │   ├─ PostCandidatureController.php     thin — delegates to the registrar
   │   └─ RegisterCandidatureRequest.php    FormRequest (input-shape validation)
   └─ Persistence/
       ├─ CandidatureModel.php              Eloquent model (never leaves this folder)
       ├─ CandidatureMapper.php             domain aggregate <-> Eloquent model
       └─ EloquentCandidatureRepository.php implements Domain\CandidatureRepository
```

**Request flow:**

```
HTTP POST /candidatures
  → RegisterCandidatureRequest      (422 here if the shape is wrong)
  → PostCandidatureController       (builds the command, no logic)
  → CandidatureRegistrar            (checks repository->existsByEmail; builds the Candidature
                                      aggregate from primitives, VOs enforce invariants, save)
  → EloquentCandidatureRepository   (mapper: aggregate → CandidatureModel → INSERT)
  ← CandidatureResponse DTO         → 201 Created JSON
```

**Email uniqueness (two-layer defense against duplicates + races):**

```
CandidatureRegistrar:
  if repository->existsByEmail(email)  → throw CandidatureAlreadyExists   (fast, happy path)
  repository->save(candidature)
    └─ EloquentCandidatureRepository catches a UNIQUE violation on `email`
       → throw CandidatureAlreadyExists                                    (race-safe guarantee)
PostCandidatureController maps CandidatureAlreadyExists → 409 Conflict.
```

The application-level `existsByEmail` check is the happy path; the DB unique index is the real
guarantee. Under concurrency two requests can both pass the check before either inserts, so only the
unique constraint prevents a duplicate — the repository translates that integrity violation into the
same domain exception. Emails are normalized to lowercase before both the check and the insert.

**Why a Registrar returning a DTO (not the aggregate):** the HTTP layer must not depend on domain
objects. The use case accepts primitives (`RegisterCandidatureCommand`) and returns primitives
(`CandidatureResponse`). The controller only translates HTTP ↔ DTO. This is what keeps "replace the
data layer without rewriting business logic" literally true.

## 2. Stack decisions

| Concern | Pick | Why |
|---|---|---|
| Identity | **ULID, generated in the domain** (`CandidatureId`, via `nextIdentity()`) | The aggregate owns its id before it ever touches the DB — no dependency on DB auto-increment, testable without persistence. ULID over UUID: lexicographically **time-sortable**, so natural chronological ordering comes for free. |
| DB column for id | `char(26)` primary key | ULIDs are 26 Crockford-base32 chars; portable across MySQL + SQLite. |
| Input validation | Laravel **FormRequest** at the HTTP boundary | Rejects malformed input (required, email format, `years >= 0`) with `422` before the domain. Business eligibility (has CV, ≥2 yrs) is a *different* capability (`candidature-validation`). |
| Email uniqueness | **Business rule**: app-level `existsByEmail` check + **DB unique index**; `409 Conflict` | Email is the candidature's business identity. App check = happy path; unique index = the real guarantee under concurrency. NOT a FormRequest `unique` rule (that would push a business rule + a DB query into the HTTP layer). |
| Invariant enforcement | **Value Objects** in the domain | Even if a caller bypasses HTTP, an invalid `Email`/`YearsOfExperience` cannot be constructed. Defense in depth, and the domain stays authoritative. |
| Aggregate ↔ storage | **Explicit mapper** class | Keeps Eloquent out of the domain. The mapper is the only place that knows both worlds. |
| Response serialization | **DTO → array** in the controller | No Eloquent `Resource` binding to domain; the DTO is already framework-agnostic primitives. |
| Namespace root | `app/Candidature/{Domain,Application,Infrastructure}` (PSR-4 `App\`) | Pragmatic — no composer changes. Purity is enforced by *imports*, not folder location (domain files import nothing from Illuminate). |

## 3. External contract (source for `docs/openapi.yaml`)

### 3.1 Request — `POST /candidatures`
```json
{
  "full_name": "Ada Lovelace",
  "email": "ada@example.com",
  "years_of_experience": 7,
  "cv": "Mathematician and first programmer. ..."
}
```
Field rules (enforced by the FormRequest):
- `full_name` — required, string, 1–255 chars.
- `email` — required, valid email, ≤ 255 chars (stored normalized to lowercase).
- `years_of_experience` — required, integer, `>= 0`.
- `cv` — required, string, non-empty.

### 3.2 Responses
- **`201 Created`** — the stored candidature:
```json
{
  "data": {
    "id": "01J9Z8K3Q7R5X2M4B6T8V0W1C2",
    "full_name": "Ada Lovelace",
    "email": "ada@example.com",
    "years_of_experience": 7,
    "cv": "Mathematician and first programmer. ...",
    "created_at": "2026-07-03T18:30:00+00:00"
  }
}
```
- **`422 Unprocessable Entity`** — Laravel's standard validation shape (malformed input):
```json
{ "message": "...", "errors": { "email": ["The email field must be a valid email address."] } }
```
- **`409 Conflict`** — the email already belongs to a registered candidature:
```json
{ "message": "A candidature with email ada@example.com already exists." }
```

### 3.3 Degradation
- No external peers consume this; no versioning concerns for this change.

## 4. Persistence

`candidatures` table (migration ships with this change):

| Column | Type | Notes |
|---|---|---|
| `id` | `char(26)` | PK (ULID) |
| `full_name` | `varchar(255)` | |
| `email` | `varchar(255)` | **unique index** (business identity; also lets later capabilities filter/sort by it) |
| `years_of_experience` | `unsigned smallint` | |
| `cv` | `text` | |
| `created_at` / `updated_at` | `timestamp` | Laravel timestamps; `created_at` is the "fecha de creación" |

## 5. Testing approach

- **Unit** — Value Objects (`Email` rejects malformed, normalizes case; `YearsOfExperience` rejects
  negatives) and the `CandidatureRegistrar` (builds and persists via an in-memory repository — a
  fake, not a mock: no internal mocks).
- **Feature/integration** — `POST /candidatures` against the real `mysql-test` database (isolated,
  tmpfs; `RefreshDatabase`): `201` + row persisted; `422` on invalid payload; `409` on a duplicate
  email (including case-insensitive duplicate), with no second row persisted. No internal mocks.

## 6. Out of scope / deferred
- Activity log / bitácora → deferred to a later change (`add-activity-log`).
- Business eligibility validation → `candidature-validation`.
- Authentication → not required by the exercise; endpoints are open.

## 7. Open questions
- None open. (Identity resolved: ULID, generated in the domain — see §2.)
