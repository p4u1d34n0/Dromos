<?php

declare(strict_types=1);

namespace Dromos\Routing;

use Closure;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;

/**
 * A PSR-15 style handler chain: each middleware wraps the next, and the last
 * one wraps the final handler. Middleware run in the order given.
 */
final class MiddlewarePipeline implements RequestHandlerInterface
{
    /**
     * @param MiddlewareInterface     $middleware
     * @param RequestHandlerInterface $next
     */
    private function __construct(
        private readonly MiddlewareInterface $middleware,
        private readonly RequestHandlerInterface $next,
    ) {
    }

    /**
     * Wrap the middleware, in order, around a final handler.
     *
     * @param MiddlewareInterface[]                                       $middleware
     * @param RequestHandlerInterface|Closure(ServerRequestInterface): ResponseInterface $final
     */
    public static function wrap(array $middleware, RequestHandlerInterface|Closure $final): RequestHandlerInterface
    {
        $handler = $final instanceof Closure ? self::handlerFor($final) : $final;
        foreach (array_reverse($middleware) as $outer) {
            $handler = new self($outer, $handler);
        }
        return $handler;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->handle($request, $this->next);
    }

    /**
     * @param Closure(ServerRequestInterface): ResponseInterface $handle
     */
    private static function handlerFor(Closure $handle): RequestHandlerInterface
    {
        return new class ($handle) implements RequestHandlerInterface {
            /**
             * @param Closure(ServerRequestInterface): ResponseInterface $handle
             */
            public function __construct(private readonly Closure $handle)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->handle)($request);
            }
        };
    }
}
