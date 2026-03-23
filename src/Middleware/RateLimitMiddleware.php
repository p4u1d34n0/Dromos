<?php

declare(strict_types=1);

namespace Dromos\Middleware;

use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Response;

/**
 * Enforces per-IP rate limiting using a sliding time window.
 *
 * Storage is delegated to a RateLimitStore implementation. By default an
 * InMemoryRateLimitStore is used, which is only effective in long-running
 * processes (OpenSwoole, ReactPHP). For PHP-FPM deployments, inject a
 * shared-storage implementation backed by Redis, APCu, or similar.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private readonly RateLimitStore $store;

    public function __construct(
        private readonly int $maxRequests = 60,
        private readonly int $windowSeconds = 60,
        ?RateLimitStore $store = null,
    ) {
        if ($maxRequests < 1) {
            throw new \InvalidArgumentException(
                'Rate limit maxRequests must be at least 1.'
            );
        }

        if ($windowSeconds < 1) {
            throw new \InvalidArgumentException(
                'Rate limit windowSeconds must be at least 1.'
            );
        }

        $this->store = $store ?? new InMemoryRateLimitStore();
    }

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? '0.0.0.0';
        $now = time();

        $entry = $this->store->increment($clientIp, $this->windowSeconds);

        $windowReset = $entry->windowStart + $this->windowSeconds;
        $remaining = max(0, $this->maxRequests - $entry->count);

        // Rate limit exceeded.
        if ($entry->count > $this->maxRequests) {
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
    private function tooManyRequestsResponse(int $retryAfter, int $windowReset): ResponseInterface
    {
        $response = new Response(429, 'Too Many Requests');

        $body = json_encode([
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Try again later.',
            'status' => 429,
        ]);

        $response->getBody()->write($body);

        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withHeader('Retry-After', (string) $retryAfter);
        $response = $response->withHeader('X-RateLimit-Limit', (string) $this->maxRequests);
        $response = $response->withHeader('X-RateLimit-Remaining', '0');
        $response = $response->withHeader('X-RateLimit-Reset', (string) $windowReset);

        return $response;
    }
}
