<?php

namespace Benchmark;

use Dromos\Http\Response;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\ResponseInterface;

/**
 * Gateway proxy for forwarding requests to backend microservices
 *
 * Uses PHP's file_get_contents with stream context rather than OpenSwoole's
 * coroutine HTTP client for simplicity and compatibility. Adds timing and
 * upstream service headers for observability.
 */
class GatewayProxy
{
    public static function forward(
        ServerRequestInterface $request,
        string $host,
        int $port,
        string $path
    ): ResponseInterface {
        $response = new Response();
        $url = "http://{$host}:{$port}{$path}";

        // Forward query string
        $query = $request->getUri()->getQuery();
        if ($query !== '') {
            $url .= '?' . $query;
        }

        $method = $request->getMethod();
        $body = (string) $request->getBody();

        $headers = "Content-Type: application/json\r\n";

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $body,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $start = microtime(true);
        $result = @file_get_contents($url, false, $context);
        $elapsed = round((microtime(true) - $start) * 1000, 2);

        if ($result === false) {
            return $response->json([
                'error' => 'Service Unavailable',
                'message' => "Failed to reach {$host}:{$port}",
                'status' => 502,
            ], 502);
        }

        // Parse upstream status from response headers
        $statusCode = 200;
        if (isset($http_response_header[0])) {
            preg_match('/\d{3}/', $http_response_header[0], $matches);
            if (!empty($matches)) {
                $statusCode = (int) $matches[0];
            }
        }

        $clone = $response->withStatus($statusCode)
                          ->withHeader('Content-Type', 'application/json')
                          ->withHeader('X-Gateway-Time', $elapsed . 'ms')
                          ->withHeader('X-Upstream-Service', $host);
        $clone->getBody()->write($result);
        return $clone;
    }
}
