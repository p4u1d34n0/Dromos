<?php

namespace Dromos;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Stream;
use Dromos\HTTP\Response;

class RouterExceptionHandler
{
    /**
     * Handle any Throwable during routing/dispatch and return a PSR-7 ResponseInterface.
     */
    public static function handle(\Throwable $e): ResponseInterface
    {
        // Log it
        error_log('[RouterException] ' . $e->getMessage());

        // Start with a fresh PSR-7 Response
        $response = new Response();

        // 1) Set status 500
        $response = $response->withStatus(500);

        // 2) Set Content-Type header
        $response = $response->withHeader('Content-Type', 'text/html; charset=UTF-8');

        // 3) Build the error body
        $body = '<h1>Internal Server Error</h1>'
            . '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</p>';

        // 4) Create a PSR-7 stream and write the body
        $stream = new Stream();
        $stream->write($body);

        // 5) Attach the stream
        $response = $response->withBody($stream);

        return $response;
    }
}
