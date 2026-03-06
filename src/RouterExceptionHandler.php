<?php

namespace Dromos;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\Stream;
use Dromos\HTTP\Response;

use Dromos\Enums\HttpStatusCodes;

/**
 * Exception handler for router-related exceptions
 * 
 * Handles exceptions thrown during routing/dispatching and converts them 
 * to appropriate PSR-7 responses.
 */
class RouterExceptionHandler
{
    /**
     * Handle any Throwable during routing/dispatch and return a PSR-7 ResponseInterface.
     *
     * @param \Throwable $e The exception to handle
     * @param int $errorCode Optional HTTP status code to use (defaults to 500)
     * @param array $args Optional additional arguments to include in the response
     * @return ResponseInterface
     */
    public static function handle(\Throwable $e, int $errorCode = 500, array $args = []): ResponseInterface
    {
        // Log it
        error_log('[RouterException] ' . $e->getMessage());

        // Start with a fresh PSR-7 Response
        $response = new Response();

        // 1) Set status code (default 500 or provided code)
        $response = $response->withStatus($errorCode);

        // 2) Set Content-Type header
        $response = $response->withHeader('Content-Type', 'application/json');

        // 3) Build the error payload
        $payload = [
            'error'   => self::getStatusText($errorCode),
            'message' => $e->getMessage(),
            'status'  => $errorCode,
        ];

        // Add additional arguments if provided
        if (!empty($args)) {
            $payload['details'] = $args;
        }

        // 4) Create a PSR-7 stream and write the body
        $stream = new Stream();
        $stream->write(json_encode($payload, JSON_UNESCAPED_SLASHES));

        // 5) Attach the stream
        $response = $response->withBody($stream);

        return $response;
    }

    /**
     * Get a text representation of an HTTP status code
     *
     * @param int $code HTTP status code
     * @return string Status text
     */
    private static function getStatusText(int $code): string
    {
        // Map of HTTP status codes to their text representation
        return HttpStatusCodes::HTTP_STATUS_CODES[$code] ?? 'Error';
    }
}
