<?php

namespace Dromos\Middleware;

use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Response;

/**
 * Class RateLimitMiddleware
 *
 * Enforces per-IP rate limiting using a sliding time window. Tracks request
 * counts in a static in-memory array. Only effective in long-running processes
 * (e.g. OpenSwoole, ReactPHP). In standard PHP-FPM, each request runs in
 * isolated memory so counters are never shared between requests. For FPM
 * deployments, implement your own middleware backed by Redis or similar.
 *
 * @package Dromos\Middleware
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * In-memory rate limit store keyed by client IP.
     *
     * Each entry is [count => int, window_start => int].
     * Resets per-process -- suitable for single-process or development use.
     *
     * @var array<string, array{count: int, window_start: int}>
     */
    protected static array $store = [];

    protected int $maxRequests;

    protected int $windowSeconds;

    /**
     * @param int $maxRequests   Maximum requests allowed per window (default: 60)
     * @param int $windowSeconds Time window in seconds (default: 60)
     */
    public function __construct(int $maxRequests = 60, int $windowSeconds = 60)
    {
        $this->maxRequests   = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $now      = time();

        // Initialise or reset the window if it has expired.
        if (
            !isset(self::$store[$clientIp])
            || ($now - self::$store[$clientIp]['window_start']) >= $this->windowSeconds
        ) {
            self::$store[$clientIp] = [
                'count'        => 0,
                'window_start' => $now,
            ];
        }

        // Garbage collect expired entries (probabilistic, ~1 in 50 requests)
        if (mt_rand(1, 50) === 1) {
            self::evictExpired($now, $this->windowSeconds);
        }

        self::$store[$clientIp]['count']++;

        $count       = self::$store[$clientIp]['count'];
        $windowStart = self::$store[$clientIp]['window_start'];
        $windowReset = $windowStart + $this->windowSeconds;
        $remaining   = max(0, $this->maxRequests - $count);

        // Rate limit exceeded.
        if ($count > $this->maxRequests) {
            $retryAfter = $windowReset - $now;

            return $this->tooManyRequestsResponse($retryAfter, $windowReset);
        }

        // Pass through and add rate limit headers to the response.
        $response = $handler->handle($request);

        $response = $response->withHeader('X-RateLimit-Limit', (string) $this->maxRequests);
        $response = $response->withHeader('X-RateLimit-Remaining', (string) $remaining);
        $response = $response->withHeader('X-RateLimit-Reset', (string) $windowReset);

        return $response;
    }

    /**
     * Build a 429 Too Many Requests JSON response.
     */
    protected function tooManyRequestsResponse(int $retryAfter, int $windowReset): ResponseInterface
    {
        $response = new Response(429, 'Too Many Requests');

        $body = json_encode([
            'error'   => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Try again later.',
            'status'  => 429,
        ]);

        $response->getBody()->write($body);

        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withHeader('Retry-After', (string) $retryAfter);
        $response = $response->withHeader('X-RateLimit-Limit', (string) $this->maxRequests);
        $response = $response->withHeader('X-RateLimit-Remaining', '0');
        $response = $response->withHeader('X-RateLimit-Reset', (string) $windowReset);

        return $response;
    }

    /**
     * Evict expired entries from the store to prevent unbounded memory growth.
     */
    private static function evictExpired(int $now, int $windowSeconds): void
    {
        foreach (self::$store as $ip => $entry) {
            if (($now - $entry['window_start']) >= $windowSeconds) {
                unset(self::$store[$ip]);
            }
        }
    }

    /**
     * Reset the in-memory store. Useful for testing.
     */
    public static function resetStore(): void
    {
        self::$store = [];
    }
}
