<?php

namespace Dromos\Http\Middleware;

use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;

/**
 * PSR-15–style middleware.
 */
interface MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response.
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler  The next request handler in the chain
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;
}
