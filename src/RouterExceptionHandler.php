<?php

namespace Dromos;

use Dromos\Enums\HttpStatusCodes;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Response;

/**
 * Exception handler for router-related exceptions
 * 
 * Handles exceptions thrown during routing/dispatching and converts them 
 * to appropriate PSR-7 responses.
 */
class RouterExceptionHandler
{
    /**
     * Log a Throwable raised during routing or dispatch and return its JSON
     * error response.
     *
     * @param \Throwable $e The exception to handle
     * @param int $errorCode Optional HTTP status code to use (defaults to 500)
     * @param array $args Optional additional arguments to include in the response
     * @return ResponseInterface
     */
    public static function handle(\Throwable $e, int $errorCode = 500, array $args = []): ResponseInterface
    {
        error_log('[RouterException] ' . $e->getMessage());

        return self::render($e, $errorCode, $args);
    }

    /**
     * The JSON error response for a Throwable, without logging it. Only a
     * RouterException's message is shown; anything else says "Internal
     * Server Error" so internals don't leak. A 405 also gets the Allow header.
     *
     * @param array $args Additional details, shown under "details"
     */
    public static function render(\Throwable $e, int $errorCode = 500, array $args = []): ResponseInterface
    {
        $payload = [
            'error'   => HttpStatusCodes::HTTP_STATUS_CODES[$errorCode] ?? 'Error',
            'message' => $e instanceof RouterException ? $e->getMessage() : 'Internal Server Error',
            'status'  => $errorCode,
        ];
        if ($args !== []) {
            $payload['details'] = $args;
        }

        // The message can echo the request path, which may not be valid UTF-8
        $response = (new Response())->json($payload, $errorCode, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($errorCode === 405 && isset($args['available_methods'])) {
            $response = $response->withHeader('Allow', implode(', ', $args['available_methods']));
        }
        return $response;
    }
}
