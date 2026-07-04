<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Response cache TTLs (seconds)
    |--------------------------------------------------------------------------
    |
    | TTLs for the cached read endpoints (capability #7, slice 1). See
    | docs/performance-notes.md for the reasoning.
    |
    | - listing: correctness comes from version-key invalidation on assignment
    |   writes, NOT from this TTL; it only reclaims orphaned keys and bounds
    |   staleness if an invalidation is ever missed.
    | - validation: a candidature is immutable, so the report never changes;
    |   a long TTL with no invalidation is safe.
    |
    */

    'listing_cache_ttl' => (int) env('LISTING_CACHE_TTL', 600),

    'validation_cache_ttl' => (int) env('VALIDATION_CACHE_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Idempotency (write endpoints, capability #7 slice 2)
    |--------------------------------------------------------------------------
    |
    | How long a processed Idempotency-Key (and its stored response) is kept so
    | retries can be replayed. Retries happen within minutes; 24h is a safe
    | upper bound.
    |
    */

    'idempotency_ttl' => (int) env('IDEMPOTENCY_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Bulk auto-assign lock (capability #7 slice 3)
    |--------------------------------------------------------------------------
    |
    | How long the exclusive 'auto-assign' lock is held at most. It is released as
    | soon as a run finishes; this TTL only auto-releases it if the process crashes
    | mid-run, so it must exceed the longest expected bulk run.
    |
    */

    'auto_assign_lock_ttl' => (int) env('AUTO_ASSIGN_LOCK_TTL', 120),

];
