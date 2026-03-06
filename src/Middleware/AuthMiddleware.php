<?php

namespace Dromos\Middleware;

use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Response;

/**
 * Class AuthMiddleware
 *
 * Authenticates incoming requests via Bearer token (Authorization header) or
 * API key (X-API-Key header). Delegates actual token validation to a
 * user-provided authenticator callable.
 *
 * @package Dromos\Middleware
 */
class AuthMiddleware implements MiddlewareInterface
{
    /** @var callable */
    protected $authenticator;

    /**
     * @param callable $authenticator A function that receives the token string and returns
     *                                a truthy value (e.g. user array/object) on success,
     *                                or null/false on failure.
     */
    public function __construct(callable $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->unauthorizedResponse('Missing authentication token');
        }

        $result = ($this->authenticator)($token);

        if (!$result) {
            return $this->unauthorizedResponse('Invalid authentication token');
        }

        $request = $request->withAttribute('auth_user', $result);

        return $handler->handle($request);
    }

    /**
     * Extract authentication token from the request.
     *
     * Checks the Authorization header for a Bearer token first,
     * then falls back to the X-API-Key header.
     */
    protected function extractToken(ServerRequestInterface $request): ?string
    {
        // Check Authorization header for Bearer token.
        $authorization = $request->getHeaderLine('Authorization');

        if ($authorization !== '' && str_starts_with($authorization, 'Bearer ')) {
            $token = substr($authorization, 7);

            if ($token !== '') {
                return $token;
            }
        }

        // Fall back to X-API-Key header.
        $apiKey = $request->getHeaderLine('X-API-Key');

        if ($apiKey !== '') {
            return $apiKey;
        }

        return null;
    }

    /**
     * Build a 401 Unauthorized JSON response.
     */
    protected function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new Response(401, 'Unauthorized');

        $body = json_encode([
            'error'   => 'Unauthorized',
            'message' => $message,
            'status'  => 401,
        ]);

        $response->getBody()->write($body);

        return $response->withHeader('Content-Type', 'application/json');
    }
}
