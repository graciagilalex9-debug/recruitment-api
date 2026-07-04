# Tasks — add-bulk-assignment-lock

Capability: **bulk-assignment-lock**. Serialize `POST /candidatures/auto-assign` with a distributed
lock so only one bulk run executes at a time; a concurrent request gets `409`. Every scenario is
covered by a test in §4.

## 1. Application — the Mutex port and the busy signal
- [x] 1.1 `Assignment/Application/Lock/Mutex` — port `withLock(string $name, Closure $callback): mixed`
  (runs the callback under an exclusive lock; throws `LockNotAcquired` if held). Template-typed return.
- [x] 1.2 `Assignment/Application/Lock/LockNotAcquired` — exception thrown by the mutex when busy.
- [x] 1.3 `Assignment/Domain/Exception/AutoAssignInProgress` — domain exception (mapped to 409).

## 2. Use case — wrap the operation
- [x] 2.1 `CandidatureAutoAssigner` — inject `Mutex`; move the current body to a private `run()`;
  `assignAll()` calls `mutex->withLock('auto-assign', fn () => $this->run())` and translates
  `LockNotAcquired` → `AutoAssignInProgress`.

## 3. Infrastructure — implementation and wiring
- [x] 3.1 `config/performance.php` — add `auto_assign_lock_ttl` (default 120), env-overridable.
- [x] 3.2 `Assignment/Infrastructure/Lock/LaravelMutex` — implements `Mutex` with `Cache::lock($name,
  $ttl)->get()` (non-blocking → `LockNotAcquired`), releasing in a `finally`; TTL from config.
- [x] 3.3 `AssignmentServiceProvider` — bind `Mutex` → `LaravelMutex`.
- [x] 3.4 `bootstrap/app.php` — map `AutoAssignInProgress` → `409`.

## 4. Tests (real mysql-test; `array` cache store)
- [x] 4.1 Feature — with the `auto-assign` lock already held, `POST /candidatures/auto-assign` → `409`.
  *(covers: a concurrent bulk run is rejected)*
- [x] 4.2 Feature — two sequential `POST /candidatures/auto-assign` both succeed (`200`) — the lock is
  released after each run. *(covers: the lock does not stay held)*
- [x] 4.3 Unit — `CandidatureAutoAssigner` with a fake `Mutex` still assigns the eligible candidatures
  (alone behaviour unchanged). *(regression)*

## 5. Docs
- [x] 5.1 `docs/openapi.yaml` — add the `409` response to `POST /candidatures/auto-assign`.
- [x] 5.2 `docs/scalability-backlog.md` — mark §3.1 (concurrent bulk auto-assign) as DONE (baseline).
- [x] 5.3 README — note the bulk auto-assign lock under "Scalability".

## 6. Validation
- [x] 6.1 `make quality` (Pint + PHPStan L8 + tests) green.
