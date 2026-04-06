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

    public function __construct(
        private readonly int $maxEntries = 10_000,
    ) {
        if ($maxEntries < 1) {
            throw new \InvalidArgumentException('InMemoryRateLimitStore maxEntries must be at least 1.');
        }
    }

    public function get(string $key): ?RateLimitEntry
    {
        $this->maybeTriggerGc();

        if (!isset($this->entries[$key])) {
            return null;
        }

        $entry = $this->entries[$key];
        $elapsed = time() - $entry['window_start'];

        if ($elapsed >= $entry['ttl']) {
            unset($this->entries[$key]);

            return null;
        }

        return new RateLimitEntry(
            count: $entry['count'],
            windowStart: $entry['window_start'],
        );
    }

    public function set(string $key, RateLimitEntry $entry, int $ttlSeconds): void
    {
        $this->entries[$key] = [
            'count' => $entry->count,
            'window_start' => $entry->windowStart,
            'ttl' => $ttlSeconds,
        ];

        $this->enforceEntryCap();
        $this->maybeTriggerGc();
    }

    public function increment(string $key, int $windowSeconds): RateLimitEntry
    {
        $now = time();
        $existing = $this->entries[$key] ?? null;

        if ($existing === null || ($now - $existing['window_start']) >= $windowSeconds) {
            $entry = new RateLimitEntry(count: 1, windowStart: $now);
        } else {
            $entry = new RateLimitEntry(
                count: $existing['count'] + 1,
                windowStart: $existing['window_start'],
            );
        }

        $this->entries[$key] = [
            'count' => $entry->count,
            'window_start' => $entry->windowStart,
            'ttl' => $windowSeconds,
        ];

        $this->enforceEntryCap();
        $this->maybeTriggerGc();

        return $entry;
    }

    private function maybeTriggerGc(): void
    {
        if (mt_rand(1, 50) === 1) {
            $this->evictExpired();
        }
    }

    /**
     * Enforce the maximum entry cap by evicting expired entries first,
     * then oldest entries if still over the limit.
     */
    private function enforceEntryCap(): void
    {
        if (count($this->entries) <= $this->maxEntries) {
            return;
        }

        $this->evictExpired();

        if (count($this->entries) <= $this->maxEntries) {
            return;
        }

        // Evict oldest entries one at a time until under cap.
        while (count($this->entries) > $this->maxEntries) {
            $oldestKey = null;
            $oldestTime = PHP_INT_MAX;

            foreach ($this->entries as $k => $e) {
                if ($e['window_start'] < $oldestTime) {
                    $oldestTime = $e['window_start'];
                    $oldestKey = $k;
                }
            }

            if ($oldestKey !== null) {
                unset($this->entries[$oldestKey]);
            }
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
