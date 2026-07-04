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

];
