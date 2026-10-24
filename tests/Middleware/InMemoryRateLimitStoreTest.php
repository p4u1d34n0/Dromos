<?php

declare(strict_types=1);

namespace Dromos\Tests\Middleware;

use Dromos\Middleware\InMemoryRateLimitStore;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryRateLimitStore::class)]
final class InMemoryRateLimitStoreTest extends TestCase
{
    private int $now = 1_000_000;

    private function store(int $maxEntries = 10_000): InMemoryRateLimitStore
    {
        return new InMemoryRateLimitStore($maxEntries, fn(): int => $this->now);
    }

    #[Test]
    public function test_counts_requests_within_a_window(): void
    {
        $store = $this->store();

        $first = $store->increment('a', 60);
        $this->now += 30;
        $second = $store->increment('a', 60);
        $this->now += 29;
        $third = $store->increment('a', 60);

        $this->assertSame([1, 2, 3], [$first->count, $second->count, $third->count]);
        $this->assertSame([1_000_000, 1_000_000, 1_000_000], [$first->windowStart, $second->windowStart, $third->windowStart]);
    }

    #[Test]
    public function test_starts_a_new_window_once_the_old_one_has_run_its_length(): void
    {
        $store = $this->store();
        $store->increment('a', 60);
        $store->increment('a', 60);

        $this->now += 60;
        $entry = $store->increment('a', 60);

        $this->assertSame(1, $entry->count);
        $this->assertSame(1_000_060, $entry->windowStart);
    }

    #[Test]
    public function test_keys_are_counted_separately(): void
    {
        $store = $this->store();
        $store->increment('a', 60);
        $store->increment('a', 60);

        $this->assertSame(1, $store->increment('b', 60)->count);
        $this->assertSame(3, $store->increment('a', 60)->count);
    }

    #[Test]
    public function test_a_full_store_drops_the_oldest_window(): void
    {
        $store = $this->store(maxEntries: 2);
        $store->increment('oldest', 60);
        $this->now++;
        $store->increment('middle', 60);
        $store->increment('middle', 60);
        $this->now++;
        $store->increment('newest', 60);

        $this->assertSame(2, $store->count());
        $this->assertSame(3, $store->increment('middle', 60)->count, 'middle should keep its count');
        $this->assertSame(2, $store->increment('newest', 60)->count, 'newest should keep its count');
        $this->assertSame(1, $store->increment('oldest', 60)->count, 'oldest should have been dropped');
    }

    #[Test]
    public function test_a_key_that_starts_a_new_window_is_no_longer_the_oldest(): void
    {
        $store = $this->store(maxEntries: 2);
        $store->increment('a', 10);         // a's window starts at t
        $this->now += 5;
        $store->increment('b', 60);         // b's window starts at t+5
        $this->now += 5;
        $store->increment('a', 10);         // a's window ran out, so it restarts at t+10
        $store->increment('a', 10);

        $store->increment('c', 60);         // full: b's window is now the oldest

        $this->assertSame(3, $store->increment('a', 10)->count, 'a restarted its window, so it should be kept');
        $this->assertSame(1, $store->increment('b', 60)->count, 'b should have been dropped');
    }

    #[Test]
    public function test_clear_forgets_every_key(): void
    {
        $store = $this->store();
        $store->increment('a', 60);
        $store->increment('a', 60);

        $store->clear();

        $this->assertSame(0, $store->count());
        $this->assertSame(1, $store->increment('a', 60)->count);
    }

    #[Test]
    public function test_expired_windows_are_eventually_swept_out(): void
    {
        $store = $this->store();
        for ($i = 0; $i < 50; $i++) {
            $store->increment("expired-$i", 10);
        }
        $this->now += 10;

        // Each new window has a 1 in 100 chance of sweeping. 2,000 new keys
        // make missing every sweep vanishingly unlikely (0.99^2000 < 1e-8).
        for ($i = 0; $i < 2_000; $i++) {
            $store->increment("fresh-$i", 3600);
        }

        $this->assertSame(2_000, $store->count(), 'only the fresh keys should remain');
    }

    #[Test]
    public function test_rejects_a_cap_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InMemoryRateLimitStore(0);
    }
}
