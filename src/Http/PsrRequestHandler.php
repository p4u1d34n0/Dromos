<?php

declare(strict_types=1);

namespace Dromos\Http;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;

/**
 * A Dromos handler, such as the Router, as a PSR-15 request handler, for
 * servers and frameworks that take one. It accepts any PSR-7 server request:
 * one from another library is copied into a Dromos request first.
 *
 * The Router doesn't implement PSR-15 itself, so requests that never use
 * PSR-15 don't load its interfaces.
 */
final class PsrRequestHandler implements PsrRequestHandlerInterface
{
    public function __construct(private readonly RequestHandlerInterface $handler)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handler->handle(Request::fromPsr($request));
    }
}
