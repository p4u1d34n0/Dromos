<?php

declare(strict_types=1);

namespace Dromos\Routing;

use Closure;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface as DromosServerRequestInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;

/**
 * A PSR-15 handler chain: each middleware wraps the next, and the last one
 * wraps the final handler. Middleware run in the order given.
 *
 * Middleware can be Dromos middleware (handle()) or PSR-15 middleware
 * (process()), as an instance or as a class name that a resolver builds
 * when the chain is wrapped. PSR-15 middleware may pass on a request, or
 * return a response, from another PSR-7 library: it's copied into a Dromos
 * one before Dromos middleware or the handler sees it (see Request::fromPsr()
 * and Response::fromPsr()).
 *
 * @phpstan-type Middleware MiddlewareInterface|PsrMiddlewareInterface
 */
final class MiddlewarePipeline implements RequestHandlerInterface, PsrRequestHandlerInterface
{
    private function __construct(
        private readonly MiddlewareInterface|PsrMiddlewareInterface $middleware,
        private readonly RequestHandlerInterface&PsrRequestHandlerInterface $next,
    ) {
    }

    /** @var array<string, true> Middleware class names already checked */
    private static array $checked = [];

    /**
     * Wrap the middleware, in order, around a final handler.
     *
     * @param array<Middleware|class-string<Middleware>>                                     $middleware
     * @param RequestHandlerInterface|Closure(DromosServerRequestInterface): ResponseInterface $final
     * @param ControllerResolverInterface|null                                          $resolver Builds middleware given by class name
     * @throws \UnexpectedValueException If the resolver doesn't return middleware
     */
    public static function wrap(
        array $middleware,
        RequestHandlerInterface|Closure $final,
        ?ControllerResolverInterface $resolver = null,
    ): RequestHandlerInterface {
        if ($middleware === [] && $final instanceof RequestHandlerInterface) {
            return $final;
        }
        // PSR-15 middleware is given the next handler, so each one must be a PSR-15 handler
        $handler = match (true) {
            $final instanceof PsrRequestHandlerInterface => $final,
            $final instanceof Closure => self::handlerFor($final),
            default => self::handlerFor($final->handle(...)),
        };
        foreach (array_reverse($middleware) as $outer) {
            $handler = new self(self::instance($outer, $resolver), $handler);
        }
        return $handler;
    }

    /**
     * @throws \InvalidArgumentException If $middleware is neither middleware
     *                                   nor the name of a class implementing it
     */
    public static function check(mixed $middleware): void
    {
        if (self::isMiddleware($middleware) || (is_string($middleware) && isset(self::$checked[$middleware]))) {
            return;
        }
        if (
            !is_string($middleware)
            || !(is_subclass_of($middleware, MiddlewareInterface::class) || is_subclass_of($middleware, PsrMiddlewareInterface::class))
        ) {
            throw new \InvalidArgumentException(
                'Route middleware must implement ' . MiddlewareInterface::class . ' or ' . PsrMiddlewareInterface::class . ', got '
                . (is_string($middleware) ? $middleware : get_debug_type($middleware)) . '.'
            );
        }
        self::$checked[$middleware] = true;
    }

    /**
     * @phpstan-assert-if-true Middleware $middleware
     */
    private static function isMiddleware(mixed $middleware): bool
    {
        return $middleware instanceof MiddlewareInterface || $middleware instanceof PsrMiddlewareInterface;
    }

    /**
     * @param Middleware|class-string<Middleware> $middleware
     * @return Middleware
     */
    private static function instance(MiddlewareInterface|PsrMiddlewareInterface|string $middleware, ?ControllerResolverInterface $resolver): MiddlewareInterface|PsrMiddlewareInterface
    {
        if (!is_string($middleware)) {
            return $middleware;
        }
        $instance = ($resolver ?? new SharedControllerResolver())->resolve($middleware);
        return self::isMiddleware($instance)
            ? $instance
            : throw new \UnexpectedValueException('The resolver returned a ' . get_debug_type($instance) . " for middleware {$middleware}.");
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->middleware instanceof MiddlewareInterface) {
            return $this->middleware->handle(Request::fromPsr($request), $this->next);
        }
        return Response::fromPsr($this->middleware->process($request, $this->next));
    }

    /**
     * @param Closure(DromosServerRequestInterface): ResponseInterface $handle
     */
    private static function handlerFor(Closure $handle): RequestHandlerInterface&PsrRequestHandlerInterface
    {
        return new class ($handle) implements RequestHandlerInterface, PsrRequestHandlerInterface {
            /**
             * @param Closure(DromosServerRequestInterface): ResponseInterface $handle
             */
            public function __construct(private readonly Closure $handle)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->handle)(Request::fromPsr($request));
            }
        };
    }
}
