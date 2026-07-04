# Tasks — add-response-caching

Capability: **response-caching**. Cache the consolidated listing and the validation report in Redis
via decorators over the read ports, keeping them consistent (version-key invalidation on assignment
writes; immutability for validation). No contract change. Every scenario is covered by a test in §4.

## 1. Pre-work — config and test cache store
- [x] 1.1 `config/performance.php` — `listing_cache_ttl` (default 600), `validation_cache_ttl`
  (default 86400), both env-overridable.
- [x] 1.2 `phpunit.xml` — add `CACHE_STORE=array` so caching is available and isolated per test.

## 2. Listing cache (Assignment context)
- [x] 2.1 `Assignment/Infrastructure/Cache/ConsolidatedListingCache` — version-key helper: `remember`
  (build `consolidated-listing:v{N}:{md5(params)}`, `Cache::remember` with the configured TTL) and
  `invalidate` (`increment` the permanent `consolidated-listing:version`); ensure the counter exists
  (`add` at 1, no TTL).
- [x] 2.2 `Assignment/Infrastructure/Persistence/CachingConsolidatedListingReader` — decorator
  implementing `ConsolidatedListingReader`, wrapping `QueryBuilderConsolidatedListingReader` + the
  cache helper.
- [x] 2.3 `Assignment/Infrastructure/Persistence/CachingAssignmentRepository` — decorator implementing
  `AssignmentRepository`, wrapping `EloquentAssignmentRepository`; after `save()`, call
  `ConsolidatedListingCache::invalidate()`. (`findByCandidature` delegates unchanged.)
- [x] 2.4 `AssignmentServiceProvider` — bind `ConsolidatedListingReader` → caching decorator wrapping
  the query-builder reader; bind `AssignmentRepository` → caching decorator wrapping the Eloquent repo.

## 3. Validation cache (Candidature context)
- [x] 3.1 `Candidature/Application/Validate/CandidatureValidationReader` — port
  `validate(string $id): ValidationReportResponse`; `CandidatureValidationFinder implements` it.
- [x] 3.2 `Candidature/Infrastructure/Cache/CachingCandidatureValidationReader` — decorator
  implementing the port, wrapping the finder with `Cache::remember("candidature-validation:{id}", ttl)`.
- [x] 3.3 `GetCandidatureValidationController` — depend on the `CandidatureValidationReader` port.
- [x] 3.4 `CandidatureServiceProvider` — bind `CandidatureValidationReader` → caching decorator
  wrapping `CandidatureValidationFinder`.

## 4. Tests (real mysql-test; `array` cache store)
- [x] 4.1 Feature — the listing reflects a newly created assignment: warm the listing, create an
  assignment via the repository, request again → the change is present. *(covers: listing stays
  consistent with assignments)*
- [x] 4.2 Feature — repeated identical listing requests return the same data and the second runs zero
  DB queries (cache hit). *(covers: listing served from cache / consistent repeats)*
- [x] 4.3 Feature — repeated validation requests return the same report and the second runs zero DB
  queries (cache hit). *(covers: validation served from cache)*

## 5. Measure and document
- [x] 5.1 Re-measure: listing cache miss vs hit (endpoint timing / query count); fill the "After"
  table in `docs/performance-notes.md` (§ Slice 1).
- [x] 5.2 `docs/scalability-backlog.md` — mark §1.1/§1.2 as DONE (baseline) and move the remaining
  bits (summary table, stampede protection) to the deferred list.
- [x] 5.3 README — note the caching layer under "Scalability".

## 6. Validation
- [x] 6.1 `make quality` (Pint + PHPStan L8 + tests) green.
