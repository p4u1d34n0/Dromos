<?php

namespace Dromos;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Stream;
use Dromos\HTTP\Response;

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
        $response = $response->withHeader('Content-Type', 'text/html; charset=UTF-8');

        // 3) Build the error body
        $body = '<h1>' . self::getStatusText($errorCode) . '</h1>' .
            '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';

        // Add additional arguments if provided
        if (!empty($args)) {
            $body .= '<h2>Additional Information</h2>';
            $body .= '<pre>' . htmlspecialchars(print_r($args, true), ENT_QUOTES) . '</pre>';
        }

        // 4) Create a PSR-7 stream and write the body
        $stream = new Stream();
        $stream->write($body);

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
        $texts = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable'
        ];

        return $texts[$code] ?? 'Error';
    }
}
