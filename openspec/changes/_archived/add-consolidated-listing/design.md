# Design — add-consolidated-listing

A pure read: candidatures that have an evaluator, enriched with per-evaluator aggregates, sortable /
filterable / paginated. CQRS read side — Query Builder returning DTOs, no domain aggregates, no writes.

## 1. Architecture (read slice, in the Assignment context)

```
app/Assignment/
├─ Application/Consolidated/
│  ├─ ConsolidatedListingQuery.php     input DTO: sort, direction, filters[], page, perPage
│  ├─ ConsolidatedRow.php              row DTO (the 7 listed fields)
│  ├─ ConsolidatedListingResult.php    result DTO: rows[] + total/perPage/currentPage/lastPage
│  └─ ConsolidatedListingReader.php    port (returns ConsolidatedListingResult; framework-agnostic)
└─ Infrastructure/
   ├─ Persistence/QueryBuilderConsolidatedListingReader.php   the query (Query Builder)
   └─ Http/
       ├─ ConsolidatedListingRequest.php   FormRequest — validates sort/direction/pagination
       ├─ GetConsolidatedListingController.php
       └─ ConsolidatedListingResource.php   data[] + meta{}
```

The controller builds a `ConsolidatedListingQuery` from the validated request and calls the reader
port directly (the query handler). No aggregates involved — this is the read model.

## 2. The query (the heart)

Per-evaluator aggregates can't be window functions (`GROUP_CONCAT` has no `OVER`), so they come from a
**derived table** joined in:

```sql
SELECT
  c.full_name, c.email, c.years_of_experience,
  e.name AS evaluator_name, a.assigned_at,
  stats.total  AS evaluator_total,
  stats.emails AS evaluator_candidate_emails
FROM assignments a
JOIN candidatures c ON c.id = a.candidature_id
JOIN evaluators   e ON e.id = a.evaluator_id
JOIN (
  SELECT a2.evaluator_id,
         COUNT(*)               AS total,
         GROUP_CONCAT(c2.email) AS emails
  FROM assignments a2
  JOIN candidatures c2 ON c2.id = a2.candidature_id
  GROUP BY a2.evaluator_id
) stats ON stats.evaluator_id = a.evaluator_id
WHERE  <whitelisted filters>
ORDER BY <whitelisted sort> <asc|desc>
LIMIT <perPage> OFFSET <(page-1)*perPage>
```

In Laravel: `DB::table('assignments as a')->join(...)->joinSub($stats, 'stats', …)->select(…)`
`->when(filters)->orderBy(…)->paginate($perPage)`. Only assigned candidatures appear (INNER JOINs).

## 3. Stack decisions

| Concern | Pick | Why |
|---|---|---|
| Read style | **Query Builder → DTOs** (CQRS read); reader port returns a framework-agnostic result | No domain aggregates for a read; Application stays Laravel-free. |
| Per-evaluator total + emails | **Derived-table subquery** joined in (`COUNT` + `GROUP_CONCAT`) | `GROUP_CONCAT` has no window form; one subquery gives both. |
| Sort | `sort` (whitelisted column) + `direction` (`asc`/`desc`), default `years_of_experience desc` | Flexible; whitelist prevents SQL injection and non-indexed surprises. |
| Filter | Whitelisted columns; **exact** for number/date, **prefix `value%`** for text | Prefix + exact use indexes; `%contains%` is avoided (full scan). See scalability backlog. |
| Unknown `sort` | **422** (validated in the FormRequest against the whitelist) | Fail loud on a bad column. |
| Unknown filter key | **ignored** (only whitelisted keys applied in the reader) | Lenient + safe (whitelist in code). |
| Pagination | Laravel `paginate()` (`per_page` default 15, capped 100; `page`) | Standard; `data` + `meta`. |
| Indexes | add `candidatures.years_of_experience` (default sort); `email`, `evaluator_id` already indexed | Sort/filter through indexes. |
| High-load hardening | **Deferred to #7** — cache, keyset pagination, GROUP_CONCAT limit, materialized aggregates | Recorded in `docs/scalability-backlog.md` §1.2, §4. |

### Column whitelist
- **Sortable:** `full_name`, `email`, `years_of_experience`, `evaluator_name`, `assigned_at`,
  `evaluator_total`. (Sorting by `evaluator_total` is a filesort — noted in the backlog §4.2.)
- **Filterable:** `full_name` (prefix), `email` (prefix), `evaluator_name` (prefix),
  `years_of_experience` (exact), `assigned_at` (exact).

## 4. External contract (source for `docs/openapi.yaml`)

### `GET /candidatures/consolidated`
Query params: `sort`, `direction` (`asc`|`desc`), `filter[<col>]=<value>`, `page`, `per_page`.

- **`200 OK`**:
```json
{
  "data": [
    {
      "full_name": "Ada Lovelace",
      "email": "ada@example.com",
      "years_of_experience": 9,
      "evaluator_name": "Grace Hopper",
      "assigned_at": "2026-07-04T10:00:00+00:00",
      "evaluator_total": 3,
      "evaluator_candidate_emails": "ada@example.com,alan@example.com,bob@example.com"
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```
- **`422`** — invalid `sort`/`direction`/pagination params.

## 5. Testing approach
- **Feature/integration** (mysql-test) — seed candidatures + evaluators + assignments, then:
  only assigned candidatures appear; each row carries the evaluator's total + concatenated emails;
  default order is years desc; sorting by another column + direction works; a prefix filter narrows
  results; pagination limits rows and reports `meta`; an invalid `sort` → `422`. GROUP_CONCAT is
  exercised on the real MySQL engine.
- (Little unit surface — this is a query; correctness lives in the integration test.)

## 6. Out of scope / deferred → `docs/scalability-backlog.md`
- Caching the listing (§1.2), keyset pagination (§4.3), GROUP_CONCAT limit / materialized aggregates
  (§4.1), filesort on aggregate sort (§4.2), full-text "contains" search (§4.4) → capability #7.
- The Excel export of this listing → capability #6.

## 7. Open questions
- None open.
