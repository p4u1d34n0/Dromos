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
 * Enforces per-IP rate limiting using a fixed time window. Counters are kept
 * in a RateLimitStore. Without one, all instances share one process-wide
 * InMemoryRateLimitStore, which is only effective in long-running processes
 * (e.g. OpenSwoole, ReactPHP). In standard PHP-FPM, each request runs in
 * isolated memory, so pass a store backed by APCu, Redis or similar.
 *
 * @package Dromos\Middleware
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /** The store used when none is given, shared by every instance. */
    private static ?InMemoryRateLimitStore $defaultStore = null;

    protected int $maxRequests;

    protected int $windowSeconds;

    private RateLimitStore $store;

    /**
     * @param int                 $maxRequests   Maximum requests allowed per window (default: 60)
     * @param int                 $windowSeconds Time window in seconds (default: 60)
     * @param RateLimitStore|null $store         Where counters are kept (default: shared, in memory)
     */
    public function __construct(int $maxRequests = 60, int $windowSeconds = 60, ?RateLimitStore $store = null)
    {
        if ($maxRequests < 1) {
            throw new \InvalidArgumentException('Rate limit maxRequests must be at least 1.');
        }
        if ($windowSeconds < 1) {
            throw new \InvalidArgumentException('Rate limit windowSeconds must be at least 1.');
        }

        $this->maxRequests   = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->store         = $store ?? (self::$defaultStore ??= new InMemoryRateLimitStore());
    }

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $clientIp = is_string($clientIp) ? $clientIp : '0.0.0.0';
        $now      = time();

        $entry       = $this->store->increment($clientIp, $this->windowSeconds);
        $count       = $entry->count;
        $windowReset = $entry->windowStart + $this->windowSeconds;
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
        ], JSON_THROW_ON_ERROR);

        $response->getBody()->write($body);

        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withHeader('Retry-After', (string) $retryAfter);
        $response = $response->withHeader('X-RateLimit-Limit', (string) $this->maxRequests);
        $response = $response->withHeader('X-RateLimit-Remaining', '0');
        $response = $response->withHeader('X-RateLimit-Reset', (string) $windowReset);

        return $response;
    }

    /**
     * Clear the shared in-memory store. Useful for testing.
     */
    public static function resetStore(): void
    {
        self::$defaultStore?->clear();
    }
}
