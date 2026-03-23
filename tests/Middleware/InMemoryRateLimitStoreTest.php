<?php

declare(strict_types=1);

namespace Dromos\Tests\Middleware;

use Dromos\Middleware\InMemoryRateLimitStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InMemoryRateLimitStore::class)]
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
        $entry = ['count' => 5, 'window_start' => time()];

        $this->store->set('192.168.1.1', $entry, 60);

        $result = $this->store->get('192.168.1.1');
        $this->assertSame($entry['count'], $result['count']);
        $this->assertSame($entry['window_start'], $result['window_start']);
    }

    public function test_it_overwrites_an_existing_entry(): void
    {
        $now = time();
        $this->store->set('192.168.1.1', ['count' => 1, 'window_start' => $now], 60);

        $this->store->set('192.168.1.1', ['count' => 10, 'window_start' => $now], 60);

        $result = $this->store->get('192.168.1.1');
        $this->assertSame(10, $result['count']);
    }

    public function test_it_returns_null_for_expired_entry(): void
    {
        $expiredStart = time() - 120;
        $this->store->set('192.168.1.1', ['count' => 5, 'window_start' => $expiredStart], 60);

        $result = $this->store->get('192.168.1.1');

        $this->assertNull($result);
    }

    public function test_it_returns_entry_within_ttl(): void
    {
        $recentStart = time() - 10;
        $this->store->set('192.168.1.1', ['count' => 3, 'window_start' => $recentStart], 60);

        $result = $this->store->get('192.168.1.1');

        $this->assertSame(3, $result['count']);
        $this->assertSame($recentStart, $result['window_start']);
    }

    public function test_it_isolates_entries_by_key(): void
    {
        $now = time();
        $this->store->set('10.0.0.1', ['count' => 1, 'window_start' => $now], 60);
        $this->store->set('10.0.0.2', ['count' => 99, 'window_start' => $now], 60);

        $this->assertSame(1, $this->store->get('10.0.0.1')['count']);
        $this->assertSame(99, $this->store->get('10.0.0.2')['count']);
    }
}
