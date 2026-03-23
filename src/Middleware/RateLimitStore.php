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
     */
    public function get(string $key): ?RateLimitEntry;

    /**
     * Store or overwrite the rate-limit entry for a given key.
     *
     * @param int $ttlSeconds Maximum lifetime hint — storage backends may use
     *                        this to auto-expire entries.
     */
    public function set(string $key, RateLimitEntry $entry, int $ttlSeconds): void;

    /**
     * Atomically increment the counter for the given key and return the updated entry.
     *
     * If the key does not exist or the window has expired, a new window is
     * started with a count of 1. Implementations backed by shared storage
     * (Redis, APCu) should use native atomic operations to avoid lost updates.
     */
    public function increment(string $key, int $windowSeconds): RateLimitEntry;
}
