<?php

namespace Dromos\Middleware;

use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Response;

/**
 * Class CorsMiddleware
 *
 * Handles Cross-Origin Resource Sharing (CORS) headers for incoming requests.
 * Returns an immediate 204 response for OPTIONS preflight requests and adds
 * CORS headers to all other responses when the origin is allowed.
 *
 * @package Dromos\Middleware
 */
class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    protected array $allowedOrigins;

    /** @var string[] */
    protected array $allowedMethods;

    /** @var string[] */
    protected array $allowedHeaders;

    protected int $maxAge;

    protected bool $allowCredentials;

    /**
     * @param array{
     *     allowed_origins?: string[],
     *     allowed_methods?: string[],
     *     allowed_headers?: string[],
     *     max_age?: int,
     *     allow_credentials?: bool
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->allowedOrigins  = $config['allowed_origins'] ?? ['*'];
        $this->allowedMethods  = $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders  = $config['allowed_headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $this->maxAge          = $config['max_age'] ?? 86400;
        $this->allowCredentials = $config['allow_credentials'] ?? false;

        if ($this->allowCredentials && in_array('*', $this->allowedOrigins, true)) {
            throw new \InvalidArgumentException(
                'CORS: allow_credentials cannot be used with wildcard allowed_origins. Specify explicit origins.'
            );
        }
    }

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // For OPTIONS preflight requests, check origin before returning 204.
        if ($request->getMethod() === 'OPTIONS') {
            if (!$this->isOriginAllowed($origin)) {
                return new Response(403, 'Forbidden');
            }
            $response = new Response(204, 'No Content');
            return $this->addCorsHeaders($response, $origin);
        }

        // For normal requests, pass through to the next handler then add CORS headers.
        $response = $handler->handle($request);

        // Only add CORS headers when an Origin header is present (cross-origin request)
        if ($origin === '') {
            return $response;
        }

        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Add CORS headers to the given response if the origin is allowed.
     */
    protected function addCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        if (!$this->isOriginAllowed($origin)) {
            return $response;
        }

        $allowOriginValue = in_array('*', $this->allowedOrigins, true) ? '*' : $origin;

        $response = $response->withHeader('Access-Control-Allow-Origin', $allowOriginValue);

        if ($allowOriginValue !== '*') {
            $response = $response->withAddedHeader('Vary', 'Origin');
        }
        $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
        $response = $response->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
        $response = $response->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Determine whether the given origin is permitted by the configured allowed origins.
     */
    protected function isOriginAllowed(string $origin): bool
    {
        // Wildcard allows all origins.
        if (in_array('*', $this->allowedOrigins, true)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins, true);
    }
}
