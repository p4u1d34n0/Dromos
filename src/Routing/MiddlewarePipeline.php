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
 *
 * Middleware can be an instance, or a class name that a resolver builds when
 * the chain is wrapped.
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

    /** @var array<string, true> Middleware class names already checked */
    private static array $checked = [];

    /**
     * Wrap the middleware, in order, around a final handler.
     *
     * @param array<MiddlewareInterface|class-string<MiddlewareInterface>>              $middleware
     * @param RequestHandlerInterface|Closure(ServerRequestInterface): ResponseInterface $final
     * @param ControllerResolverInterface|null                                          $resolver Builds middleware given by class name
     * @throws \UnexpectedValueException If the resolver doesn't return middleware
     */
    public static function wrap(
        array $middleware,
        RequestHandlerInterface|Closure $final,
        ?ControllerResolverInterface $resolver = null,
    ): RequestHandlerInterface {
        $handler = $final instanceof Closure ? self::handlerFor($final) : $final;
        foreach (array_reverse($middleware) as $outer) {
            $handler = new self(self::instance($outer, $resolver), $handler);
        }
        return $handler;
    }

    /**
     * @throws \InvalidArgumentException If $middleware is neither a MiddlewareInterface
     *                                   nor the name of a class implementing it
     */
    public static function check(mixed $middleware): void
    {
        if ($middleware instanceof MiddlewareInterface || (is_string($middleware) && isset(self::$checked[$middleware]))) {
            return;
        }
        if (!is_string($middleware) || !is_subclass_of($middleware, MiddlewareInterface::class)) {
            throw new \InvalidArgumentException(
                'Route middleware must implement ' . MiddlewareInterface::class . ', got '
                . (is_string($middleware) ? $middleware : get_debug_type($middleware)) . '.'
            );
        }
        self::$checked[$middleware] = true;
    }

    /**
     * @param MiddlewareInterface|class-string<MiddlewareInterface> $middleware
     */
    private static function instance(MiddlewareInterface|string $middleware, ?ControllerResolverInterface $resolver): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }
        $instance = ($resolver ?? new SharedControllerResolver())->resolve($middleware);
        return $instance instanceof MiddlewareInterface
            ? $instance
            : throw new \UnexpectedValueException('The resolver returned a ' . get_debug_type($instance) . " for middleware {$middleware}.");
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
