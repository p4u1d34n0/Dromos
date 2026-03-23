<?php

declare(strict_types=1);

namespace Dromos\Tests\Middleware;

use Dromos\Middleware\InMemoryRateLimitStore;
use Dromos\Middleware\RateLimitEntry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryRateLimitStore::class)]
#[CoversClass(RateLimitEntry::class)]
final class InMemoryRateLimitStoreTest extends TestCase
{
    private InMemoryRateLimitStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryRateLimitStore();
    }

    public function test_it_returns_null_for_unknown_key(): void
    {
        $result = $this->store->get('192.168.1.1');

        $this->assertNull($result);
    }

    public function test_it_stores_and_retrieves_an_entry(): void
    {
        $now = time();
        $entry = new RateLimitEntry(count: 5, windowStart: $now);

        $this->store->set('192.168.1.1', $entry, 60);

        $result = $this->store->get('192.168.1.1');
        $this->assertInstanceOf(RateLimitEntry::class, $result);
        $this->assertSame(5, $result->count);
        $this->assertSame($now, $result->windowStart);
    }

    public function test_it_overwrites_an_existing_entry(): void
    {
        $now = time();
        $this->store->set('192.168.1.1', new RateLimitEntry(count: 1, windowStart: $now), 60);

        $this->store->set('192.168.1.1', new RateLimitEntry(count: 10, windowStart: $now), 60);

        $result = $this->store->get('192.168.1.1');
        $this->assertSame(10, $result->count);
    }

    public function test_it_returns_null_for_expired_entry(): void
    {
        $expiredStart = time() - 120;
        $this->store->set('192.168.1.1', new RateLimitEntry(count: 5, windowStart: $expiredStart), 60);

        $result = $this->store->get('192.168.1.1');

        $this->assertNull($result);
    }

    public function test_it_returns_entry_within_ttl(): void
    {
        $recentStart = time() - 10;
        $this->store->set('192.168.1.1', new RateLimitEntry(count: 3, windowStart: $recentStart), 60);

        $result = $this->store->get('192.168.1.1');

        $this->assertSame(3, $result->count);
        $this->assertSame($recentStart, $result->windowStart);
    }

    public function test_it_isolates_entries_by_key(): void
    {
        $now = time();
        $this->store->set('10.0.0.1', new RateLimitEntry(count: 1, windowStart: $now), 60);
        $this->store->set('10.0.0.2', new RateLimitEntry(count: 99, windowStart: $now), 60);

        $this->assertSame(1, $this->store->get('10.0.0.1')->count);
        $this->assertSame(99, $this->store->get('10.0.0.2')->count);
    }

    public function test_increment_creates_new_entry_for_unknown_key(): void
    {
        $entry = $this->store->increment('192.168.1.1', 60);

        $this->assertInstanceOf(RateLimitEntry::class, $entry);
        $this->assertSame(1, $entry->count);
        $this->assertEqualsWithDelta(time(), $entry->windowStart, 1);
    }

    public function test_increment_increments_existing_entry(): void
    {
        $this->store->increment('192.168.1.1', 60);

        $entry = $this->store->increment('192.168.1.1', 60);

        $this->assertSame(2, $entry->count);
    }

    public function test_increment_resets_window_when_expired(): void
    {
        $expiredStart = time() - 120;
        $this->store->set('192.168.1.1', new RateLimitEntry(count: 50, windowStart: $expiredStart), 60);

        $entry = $this->store->increment('192.168.1.1', 60);

        $this->assertSame(1, $entry->count);
        $this->assertEqualsWithDelta(time(), $entry->windowStart, 1);
    }

    public function test_increment_persists_entry_in_store(): void
    {
        $this->store->increment('192.168.1.1', 60);

        $result = $this->store->get('192.168.1.1');

        $this->assertNotNull($result);
        $this->assertSame(1, $result->count);
    }

    public function test_it_evicts_entries_when_max_entries_exceeded(): void
    {
        $store = new InMemoryRateLimitStore(maxEntries: 3);
        $now = time();

        $store->set('10.0.0.1', new RateLimitEntry(count: 1, windowStart: $now - 30), 60);
        $store->set('10.0.0.2', new RateLimitEntry(count: 1, windowStart: $now - 20), 60);
        $store->set('10.0.0.3', new RateLimitEntry(count: 1, windowStart: $now - 10), 60);

        // Adding a 4th entry should evict the oldest (10.0.0.1).
        $store->set('10.0.0.4', new RateLimitEntry(count: 1, windowStart: $now), 60);

        $this->assertNull($store->get('10.0.0.1'));
        $this->assertNotNull($store->get('10.0.0.4'));
    }

    public function test_it_evicts_expired_entries_before_oldest_when_over_cap(): void
    {
        $store = new InMemoryRateLimitStore(maxEntries: 3);
        $now = time();

        // One expired entry and two active ones.
        $store->set('10.0.0.1', new RateLimitEntry(count: 1, windowStart: $now - 200), 60);
        $store->set('10.0.0.2', new RateLimitEntry(count: 1, windowStart: $now - 10), 60);
        $store->set('10.0.0.3', new RateLimitEntry(count: 1, windowStart: $now - 5), 60);

        // Adding a 4th: expired entry is evicted first, bringing count to 3.
        $store->set('10.0.0.4', new RateLimitEntry(count: 1, windowStart: $now), 60);

        $this->assertNull($store->get('10.0.0.1'));
        $this->assertNotNull($store->get('10.0.0.2'));
        $this->assertNotNull($store->get('10.0.0.3'));
        $this->assertNotNull($store->get('10.0.0.4'));
    }
}
