<?php

declare(strict_types=1);

namespace Dromos\Middleware;

use Closure;
use InvalidArgumentException;

/**
 * Keeps rate-limit counters in a PHP array, for the life of the process.
 *
 * - Entries are kept in the order their windows started: a key that starts a
 *   new window moves to the end. When the store is full, the oldest window is
 *   dropped in O(1), so a flood of new keys can't make each request slower.
 * - About one call in 100 also sweeps out expired windows.
 */
final class InMemoryRateLimitStore implements RateLimitStore
{
    /** One sweep for expired windows per this many calls, on average. */
    private const SWEEP_ODDS = 100;

    /** @var array<string, array{count: int, start: int, expires: int}> */
    private array $entries = [];

    /** @var Closure(): int */
    private readonly Closure $clock;

    /**
     * @param int                 $maxEntries Most keys to track at once; the oldest window is dropped beyond this
     * @param (Closure(): int)|null $clock    Current Unix time; defaults to time()
     */
    public function __construct(
        private readonly int $maxEntries = 10_000,
        ?Closure $clock = null,
    ) {
        if ($maxEntries < 1) {
            throw new InvalidArgumentException('InMemoryRateLimitStore maxEntries must be at least 1.');
        }
        $this->clock = $clock ?? time(...);
    }

    public function increment(string $key, int $windowSeconds): RateLimitEntry
    {
        $now = ($this->clock)();
        $entry = $this->entries[$key] ?? null;

        if ($entry !== null && $now - $entry['start'] < $windowSeconds) {
            $count = ++$this->entries[$key]['count'];
            return new RateLimitEntry($count, $entry['start']);
        }

        // A new window: move the key to the end so the array stays in start order
        unset($this->entries[$key]);
        $this->entries[$key] = ['count' => 1, 'start' => $now, 'expires' => $now + $windowSeconds];

        if (count($this->entries) > $this->maxEntries) {
            unset($this->entries[array_key_first($this->entries)]);
        }
        if (mt_rand(1, self::SWEEP_ODDS) === 1) {
            $this->sweep($now);
        }

        return new RateLimitEntry(1, $now);
    }

    /** The number of keys being tracked. */
    public function count(): int
    {
        return count($this->entries);
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * Drop expired windows. Windows can have different lengths, so an
     * expired one isn't always at the front.
     */
    private function sweep(int $now): void
    {
        foreach ($this->entries as $key => $entry) {
            if ($entry['expires'] <= $now) {
                unset($this->entries[$key]);
            }
        }
    }
}
