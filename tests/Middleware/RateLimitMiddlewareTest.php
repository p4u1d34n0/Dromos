<?php

declare(strict_types=1);

namespace Dromos\Tests\Middleware;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Middleware\InMemoryRateLimitStore;
use Dromos\Middleware\RateLimitMiddleware;
use Dromos\Middleware\RateLimitStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitMiddleware::class)]
final class RateLimitMiddlewareTest extends TestCase
{
    private FakeRequestHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new FakeRequestHandler();
    }

    public function test_it_passes_request_through_when_under_limit(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 5, windowSeconds: 60);
        $request = $this->createRequest('10.0.0.1');

        $response = $middleware->handle($request, $this->handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_returns_429_when_limit_exceeded(): void
    {
        $store = new InMemoryRateLimitStore();
        $middleware = new RateLimitMiddleware(maxRequests: 3, windowSeconds: 60, store: $store);
        $request = $this->createRequest('10.0.0.1');

        // Exhaust the limit.
        for ($i = 0; $i < 3; $i++) {
            $middleware->handle($request, $this->handler);
        }

        // Next request should be rejected.
        $response = $middleware->handle($request, $this->handler);

        $this->assertSame(429, $response->getStatusCode());
    }

    public function test_it_adds_rate_limit_headers_to_successful_response(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 10, windowSeconds: 60);
        $request = $this->createRequest('10.0.0.1');

        $response = $middleware->handle($request, $this->handler);

        $this->assertSame('10', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('9', $response->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertNotEmpty($response->getHeaderLine('X-RateLimit-Reset'));
    }

    public function test_it_adds_retry_after_header_to_429_response(): void
    {
        $store = new InMemoryRateLimitStore();
        $middleware = new RateLimitMiddleware(maxRequests: 1, windowSeconds: 120, store: $store);
        $request = $this->createRequest('10.0.0.1');

        // Use up the single allowed request.
        $middleware->handle($request, $this->handler);

        // Second request triggers 429.
        $response = $middleware->handle($request, $this->handler);

        $this->assertSame(429, $response->getStatusCode());
        $retryAfter = (int) $response->getHeaderLine('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(120, $retryAfter);
        $this->assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function test_it_tracks_limits_per_ip_independently(): void
    {
        $store = new InMemoryRateLimitStore();
        $middleware = new RateLimitMiddleware(maxRequests: 1, windowSeconds: 60, store: $store);

        // First IP uses its single request.
        $middleware->handle($this->createRequest('10.0.0.1'), $this->handler);

        // Second IP should still get through.
        $response = $middleware->handle($this->createRequest('10.0.0.2'), $this->handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_resets_counter_after_window_expires(): void
    {
        $store = new FakeRateLimitStore();
        $middleware = new RateLimitMiddleware(maxRequests: 1, windowSeconds: 60, store: $store);
        $request = $this->createRequest('10.0.0.1');

        // First request goes through.
        $middleware->handle($request, $this->handler);

        // Simulate the window expiring by backdating the stored entry.
        $entry = $store->get('10.0.0.1');
        $store->set('10.0.0.1', [
            'count' => $entry['count'],
            'window_start' => time() - 120,
        ], 60);

        // After expiry the middleware should treat this as a fresh window.
        $response = $middleware->handle($request, $this->handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_defaults_to_in_memory_store_when_none_provided(): void
    {
        $middleware = new RateLimitMiddleware(maxRequests: 5, windowSeconds: 60);
        $request = $this->createRequest('10.0.0.1');

        // Should work without exploding — proves the default store is wired up.
        $response = $middleware->handle($request, $this->handler);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_uses_injected_store(): void
    {
        $store = new FakeRateLimitStore();
        $middleware = new RateLimitMiddleware(maxRequests: 5, windowSeconds: 60, store: $store);
        $request = $this->createRequest('10.0.0.1');

        $middleware->handle($request, $this->handler);

        // The injected store should have received the entry.
        $entry = $store->get('10.0.0.1');
        $this->assertNotNull($entry);
        $this->assertSame(1, $entry['count']);
    }

    public function test_it_decrements_remaining_count_on_each_request(): void
    {
        $store = new InMemoryRateLimitStore();
        $middleware = new RateLimitMiddleware(maxRequests: 3, windowSeconds: 60, store: $store);
        $request = $this->createRequest('10.0.0.1');

        $first = $middleware->handle($request, $this->handler);
        $second = $middleware->handle($request, $this->handler);

        $this->assertSame('2', $first->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertSame('1', $second->getHeaderLine('X-RateLimit-Remaining'));
    }

    private function createRequest(string $clientIp): ServerRequestInterface
    {
        return Request::create(
            method: 'GET',
            uri: '/',
            serverParams: ['REMOTE_ADDR' => $clientIp],
        );
    }
}

/**
 * Minimal request handler that always returns a 200 response.
 */
final class FakeRequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200, 'OK');
    }
}

/**
 * Transparent rate-limit store for testing — stores entries without TTL expiry.
 */
final class FakeRateLimitStore implements RateLimitStore
{
    /** @var array<string, array{count: int, window_start: int}> */
    private array $entries = [];

    public function get(string $key): ?array
    {
        return $this->entries[$key] ?? null;
    }

    public function set(string $key, array $entry, int $ttlSeconds): void
    {
        $this->entries[$key] = $entry;
    }
}
