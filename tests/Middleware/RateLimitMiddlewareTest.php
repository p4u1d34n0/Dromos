<?php

declare(strict_types=1);

namespace Dromos\Tests\Middleware;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Middleware\RateLimitEntry;
use Dromos\Middleware\RateLimitMiddleware;
use Dromos\Middleware\RateLimitStore;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimitMiddleware::class)]
final class RateLimitMiddlewareTest extends TestCase
{
    private int $handled = 0;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimitMiddleware::resetStore();
    }

    protected function tearDown(): void
    {
        RateLimitMiddleware::resetStore();
        parent::tearDown();
    }

    private function send(RateLimitMiddleware $middleware, string $ip = '203.0.113.7'): ResponseInterface
    {
        $handler = new class ($this->handled) implements RequestHandlerInterface {
            public function __construct(private int &$handled)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->handled++;
                return (new Response())->json(['ok' => true]);
            }
        };

        return $middleware->handle(Request::create('GET', '/', ['REMOTE_ADDR' => $ip]), $handler);
    }

    #[Test]
    public function test_passes_requests_under_the_limit_and_counts_down_remaining(): void
    {
        $middleware = new RateLimitMiddleware(3, 60);

        $remaining = [];
        for ($i = 0; $i < 3; $i++) {
            $response = $this->send($middleware);
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('3', $response->getHeaderLine('X-RateLimit-Limit'));
            $remaining[] = $response->getHeaderLine('X-RateLimit-Remaining');
        }

        $this->assertSame(['2', '1', '0'], $remaining);
        $this->assertSame(3, $this->handled);
    }

    #[Test]
    public function test_rejects_the_request_after_the_limit_without_calling_the_handler(): void
    {
        $middleware = new RateLimitMiddleware(2, 60);
        $before = time();
        $this->send($middleware);
        $this->send($middleware);

        $response = $this->send($middleware);
        $after = time();

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame(2, $this->handled);
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            ['error' => 'Too Many Requests', 'message' => 'Rate limit exceeded. Try again later.', 'status' => 429],
            json_decode((string) $response->getBody(), true),
        );
        $this->assertSame('2', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));

        $reset = (int) $response->getHeaderLine('X-RateLimit-Reset');
        $this->assertGreaterThanOrEqual($before + 60, $reset);
        $this->assertLessThanOrEqual($after + 60, $reset);
        $retryAfter = (int) $response->getHeaderLine('Retry-After');
        // Retry-After is the seconds from the moment of the request to the reset
        $this->assertGreaterThanOrEqual($reset - $after, $retryAfter);
        $this->assertLessThanOrEqual($reset - $before, $retryAfter);
    }

    #[Test]
    public function test_limits_each_client_ip_separately(): void
    {
        $middleware = new RateLimitMiddleware(1, 60);

        $this->assertSame(200, $this->send($middleware, '198.51.100.1')->getStatusCode());
        $this->assertSame(429, $this->send($middleware, '198.51.100.1')->getStatusCode());
        $this->assertSame(200, $this->send($middleware, '198.51.100.2')->getStatusCode());
    }

    #[Test]
    public function test_uses_the_given_store_and_its_window(): void
    {
        $store = new class implements RateLimitStore {
            /** @var list<array{string, int}> */
            public array $calls = [];

            public function increment(string $key, int $windowSeconds): RateLimitEntry
            {
                $this->calls[] = [$key, $windowSeconds];
                return new RateLimitEntry(5, 1_700_000_000);
            }
        };

        $response = $this->send(new RateLimitMiddleware(10, 30, $store), '192.0.2.9');

        $this->assertSame([['192.0.2.9', 30]], $store->calls);
        $this->assertSame('5', $response->getHeaderLine('X-RateLimit-Remaining'));
        $this->assertSame('1700000030', $response->getHeaderLine('X-RateLimit-Reset'));
    }

    #[Test]
    public function test_instances_without_a_store_share_their_counters(): void
    {
        $first = new RateLimitMiddleware(2, 60);
        $second = new RateLimitMiddleware(2, 60);

        $this->send($first);
        $this->send($second);

        $this->assertSame(429, $this->send($first)->getStatusCode());
    }

    #[Test]
    public function test_reset_store_clears_the_shared_counters(): void
    {
        $middleware = new RateLimitMiddleware(1, 60);
        $this->send($middleware);
        $this->assertSame(429, $this->send($middleware)->getStatusCode());

        RateLimitMiddleware::resetStore();

        $this->assertSame(200, $this->send($middleware)->getStatusCode());
    }

    /** @return array<string, array{int, int}> */
    public static function invalidLimits(): array
    {
        return [
            'no requests' => [0, 60],
            'negative requests' => [-1, 60],
            'no window' => [60, 0],
        ];
    }

    #[Test]
    #[DataProvider('invalidLimits')]
    public function test_rejects_limits_below_one(int $maxRequests, int $windowSeconds): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RateLimitMiddleware($maxRequests, $windowSeconds);
    }
}
