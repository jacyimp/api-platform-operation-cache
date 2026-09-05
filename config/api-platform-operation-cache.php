<?php

declare(strict_types=1);

return [
    /*
     * Laravel cache store used by operation caching.
     *
     * Null uses the application's default cache store.
     */
    'store' => null,

    /*
     * Request headers included in every operation cache key unless the
     * operation sets includeDefaultVary to false.
     */
    'vary_by_headers' => [],
];
