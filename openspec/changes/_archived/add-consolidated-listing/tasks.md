# Tasks — add-consolidated-listing

Capability: **consolidated-listing**. Pure CQRS read (no domain aggregates, no writes). Lives in the
Assignment context. Every scenario is covered by a test (see §6).

## 1. Application — read model (framework-agnostic)
- [x] 1.1 `Assignment/Application/Consolidated/ConsolidatedListingQuery` — input DTO: `sort`,
  `direction`, `filters` (array<string,string>), `page`, `perPage`.
- [x] 1.2 `Assignment/Application/Consolidated/ConsolidatedRow` — row DTO (the 7 listed fields).
- [x] 1.3 `Assignment/Application/Consolidated/ConsolidatedListingResult` — DTO: `list<ConsolidatedRow>`
  + `total`, `perPage`, `currentPage`, `lastPage`.
- [x] 1.4 `Assignment/Application/Consolidated/ConsolidatedListingReader` — port returning
  `ConsolidatedListingResult` from a `ConsolidatedListingQuery`.

## 2. Infrastructure — Persistence (the query)
- [x] 2.1 Migration: add index `candidatures.years_of_experience` (default sort column).
- [x] 2.2 `Assignment/Infrastructure/Persistence/QueryBuilderConsolidatedListingReader` — INNER JOIN
  candidatures+assignments+evaluators + `joinSub` derived table (`COUNT` + `GROUP_CONCAT` per
  evaluator); apply whitelisted filter (exact / prefix) and sort (default `years_of_experience desc`);
  `paginate`; map rows → `ConsolidatedRow` and the paginator → `ConsolidatedListingResult`.

## 3. Infrastructure — HTTP
- [x] 3.1 `Assignment/Infrastructure/Http/ConsolidatedListingRequest` (FormRequest) — `sort` in the
  sortable whitelist, `direction` in `asc,desc`, `per_page` integer 1..100, `page` integer >= 1.
- [x] 3.2 `Assignment/Infrastructure/Http/GetConsolidatedListingController` — build the query DTO from
  validated input, call the reader, return the resource.
- [x] 3.3 `Assignment/Infrastructure/Http/ConsolidatedListingResource` — `data[]` + `meta{current_page,
  per_page, total, last_page}`.
- [x] 3.4 Route `GET /candidatures/consolidated` in `routes/api.php`.

## 4. DI wiring
- [x] 4.1 Bind `ConsolidatedListingReader` → `QueryBuilderConsolidatedListingReader` in
  `AssignmentServiceProvider`.

## 5. API contract
- [x] 5.1 Add `GET /candidatures/consolidated` to `docs/openapi.yaml` (`200` paginated + `422`).

## 6. Tests (real mysql-test — exercises GROUP_CONCAT on the real engine)
- [x] 6.1 Feature — assigned candidatures returned with evaluator name, assignment date, evaluator
  total and concatenated emails. *(covers: enriched with evaluator context)*
- [x] 6.2 Feature — candidatures without an evaluator are excluded. *(covers: unassigned excluded)*
- [x] 6.3 Feature — default order is years of experience descending. *(covers: default order)*
- [x] 6.4 Feature — ordering by a chosen column and direction. *(covers: chosen order)*
- [x] 6.5 Feature — filtering by a listed column narrows the results. *(covers: filtering)*
- [x] 6.6 Feature — pagination limits rows and reports `meta`. *(covers: pagination)*
- [x] 6.7 Feature — an unknown `sort` column → `422`. *(covers: invalid listing request)*

## 7. Validation and cleanup
- [x] 7.1 `make quality` (Pint + PHPStan L8 + tests) green.
- [x] 7.2 README: document `GET /candidatures/consolidated`; roadmap update.
