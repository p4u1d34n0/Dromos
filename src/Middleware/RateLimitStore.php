<?php

declare(strict_types=1);

namespace Dromos\Middleware;

/**
 * Where RateLimitMiddleware keeps its counters.
 *
 * The bundled InMemoryRateLimitStore keeps them in process memory, which only
 * works in a long-running server (OpenSwoole, RoadRunner, ReactPHP). Under
 * PHP-FPM every request has its own memory, so implement this interface over
 * shared storage such as APCu or Redis.
 */
interface RateLimitStore
{
    /**
     * Count one request for the key and return its window.
     *
     * If the key has no window, or its window started $windowSeconds or more
     * ago, a new window starts now with a count of 1. Implementations over
     * shared storage should do this atomically, so concurrent requests are
     * never lost.
     */
    public function increment(string $key, int $windowSeconds): RateLimitEntry;
}
