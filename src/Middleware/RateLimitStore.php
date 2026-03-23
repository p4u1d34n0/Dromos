<?php

declare(strict_types=1);

namespace Dromos\Middleware;

/**
 * Storage backend for rate-limit counters.
 *
 * Implement this interface to plug in shared storage (Redis, APCu, database,
 * etc.) so that rate limiting works across PHP-FPM requests. The bundled
 * InMemoryRateLimitStore keeps counters in process memory and is only
 * suitable for long-running servers or development.
 */
interface RateLimitStore
{
    /**
     * Retrieve the rate-limit entry for a given key.
     *
     * @return array{count: int, window_start: int}|null
     */
    public function get(string $key): ?array;

    /**
     * Store or overwrite the rate-limit entry for a given key.
     *
     * @param array{count: int, window_start: int} $entry
     * @param int $ttlSeconds Maximum lifetime hint — storage backends may use
     *                        this to auto-expire entries.
     */
    public function set(string $key, array $entry, int $ttlSeconds): void;
}
