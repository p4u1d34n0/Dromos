<?php

declare(strict_types=1);

namespace Dromos\Middleware;

/**
 * In-memory rate-limit store backed by a plain PHP array.
 *
 * Counters live only for the lifetime of the current process, making this
 * suitable for long-running servers (OpenSwoole, ReactPHP) and local
 * development. For PHP-FPM deployments, inject a shared-storage
 * implementation of RateLimitStore instead.
 */
final class InMemoryRateLimitStore implements RateLimitStore
{
    /** @var array<string, array{count: int, window_start: int, ttl: int}> */
    private array $entries = [];

    public function get(string $key): ?array
    {
        if (!isset($this->entries[$key])) {
            return null;
        }

        $entry = $this->entries[$key];
        $elapsed = time() - $entry['window_start'];

        if ($elapsed >= $entry['ttl']) {
            unset($this->entries[$key]);

            return null;
        }

        return [
            'count' => $entry['count'],
            'window_start' => $entry['window_start'],
        ];
    }

    public function set(string $key, array $entry, int $ttlSeconds): void
    {
        $this->entries[$key] = [
            'count' => $entry['count'],
            'window_start' => $entry['window_start'],
            'ttl' => $ttlSeconds,
        ];

        // Probabilistic garbage collection (~1 in 50 writes).
        if (mt_rand(1, 50) === 1) {
            $this->evictExpired();
        }
    }

    private function evictExpired(): void
    {
        $now = time();

        foreach ($this->entries as $key => $entry) {
            if (($now - $entry['window_start']) >= $entry['ttl']) {
                unset($this->entries[$key]);
            }
        }
    }
}
