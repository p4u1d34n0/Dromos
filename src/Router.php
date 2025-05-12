<?php

namespace Dromos;

use Closure;
use Dromos\Middleware\RequestParameters;
use Dromos\HTTP\Request;    // implements ServerRequestInterface
use Dromos\HTTP\Response;   // implements ResponseInterface
use Dromos\RouteResource;
use Dromos\RouterException;
use Dromos\RouterExceptionHandler;

use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;

use Dromos\Traits\RouteCacheTrait;


/**
 * PSR-15–style Router for Dromos
 */
class Router implements RequestHandlerInterface
{

    use RouteCacheTrait;

    /** @var array<int,array{method:string,path:string,handler:mixed}> */
    protected static array $routes = [];

    /** @var array<int,array> */
    private static array $missingRoutes = [];

    /** @var MiddlewareInterface[] */
    private array $middlewareStack = [];

    // ────────────────────────────────────────────────────────────────────────────────
    // Route registration (static)
    // ────────────────────────────────────────────────────────────────────────────────

    public static function Get(string $url, $target): string
    {
        return self::addRoute('GET',    $url, $target);
    }
    public static function Post(string $url, $target): string
    {
        return self::addRoute('POST',   $url, $target);
    }
    public static function Put(string $url, $target): string
    {
        return self::addRoute('PUT',    $url, $target);
    }
    public static function Head(string $url, $target): string
    {
        return self::addRoute('HEAD',   $url, $target);
    }
    public static function Options(string $url, $target): string
    {
        return self::addRoute('OPTIONS', $url, $target);
    }

    public static function Resource(string $url, string $controller): RouteResource
    {
        $resource = new RouteResource($url, $controller);
        $resource->register();
        return $resource;
    }

    protected static function addRoute(string $method, string $url, $target): string
    {
        $route = [
            'method'  => strtoupper($method),
            'path'    => $url,
            'handler' => self::checkTarget($target),
        ];

        self::$routes[$method][$url] = $route['handler'];

        // Track the route for caching
        self::trackRouteForCache($route);

        return self::class;
    }


    /** @return Closure|array */
    protected static function checkTarget($target)
    {
        if ($target instanceof Closure) {
            return $target;
        }
        if (is_array($target) && count($target) === 2 && is_string($target[0])) {
            $controller = new $target[0]();
            if (!method_exists($controller, $target[1])) {
                self::$missingRoutes[] = [$controller, $target[1]];
            }
            return [$controller, $target[1]];
        }
        throw new \InvalidArgumentException("Invalid route target for URL.");
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Middleware registration (instance)
    // ────────────────────────────────────────────────────────────────────────────────

    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middlewareStack[] = $middleware;
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Entry point (implements RequestHandlerInterface)
    // ────────────────────────────────────────────────────────────────────────────────

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Build pipeline beginning at index 0
        $pipeline = $this->createMiddlewareHandler(0);
        return $pipeline->handle($request);
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Recursively build the middleware → final dispatch pipeline
    // ────────────────────────────────────────────────────────────────────────────────

    private function createMiddlewareHandler(int $index): RequestHandlerInterface
    {
        // If there's more middleware, wrap it around the “next” handler
        if ($index < count($this->middlewareStack)) {
            $mw   = $this->middlewareStack[$index];
            $next = $this->createMiddlewareHandler($index + 1);

            return new class($mw, $next) implements RequestHandlerInterface {
                private MiddlewareInterface    $middleware;
                private RequestHandlerInterface $nextHandler;

                public function __construct(MiddlewareInterface $mw, RequestHandlerInterface $next)
                {
                    $this->middleware  = $mw;
                    $this->nextHandler = $next;
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->handle($request, $this->nextHandler);
                }
            };
        }

        // Base case: no more middleware → dispatch the route
        return new class($this) implements RequestHandlerInterface {
            private Router $router;
            public function __construct(Router $router)
            {
                $this->router = $router;
            }
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->router->dispatch($request);
            }
        };
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Core dispatch logic (instance method)
    // ────────────────────────────────────────────────────────────────────────────────

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $method = $request->getMethod();
            $path   = $request->getUri()->getPath();

            // Determine the routes to use (cached or standard)
            $routes = self::$useCache ? self::$compiledRoutes : self::$routes;

            if (! isset($routes[$method])) {
                throw new RouterException("Method not allowed: $method");
            }

            foreach ($routes[$method] as $routeUrl => $target) {
                // Exact match
                if ($routeUrl === $path) {
                    return $this->invokeTarget($target, [], $request);
                }

                // Parameterized match
                $params = $this->extractParams($routeUrl, $path);
                if (! empty($params) && self::reconstructUrl($routeUrl, $params) === $path) {
                    return $this->invokeTarget($target, $params, $request);
                }
            }

            throw new RouterException("Route not found: $path");
        } catch (\Throwable $e) {
            return RouterExceptionHandler::handle($e);
        }
    }


    // ────────────────────────────────────────────────────────────────────────────────
    // Extract route parameters based on placeholders
    // ────────────────────────────────────────────────────────────────────────────────

    private function extractParams(string $route, string $path): array
    {
        // Extract parameter names
        preg_match_all('/\{([^}]+)\}/', $route, $matches);
        $names = $matches[1] ?? [];

        // Build regex
        $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route);

        if (! preg_match("#^{$pattern}$#", $path, $values)) {
            return [];
        }

        array_shift($values); // drop full match
        return array_combine($names, $values);
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Invoke route target (Closure or [Controller,method])
    // ────────────────────────────────────────────────────────────────────────────────

    private function invokeTarget($target, array $params, ServerRequestInterface $request): ResponseInterface
    {
        // Inject route parameters as request attributes
        foreach ($params as $key => $val) {
            $request = $request->withAttribute($key, $val);
        }

        // Build argument list via reflection
        $args = [];
        if ($target instanceof Closure) {
            $ref = new \ReflectionFunction($target);
        } else {
            $ref = new \ReflectionMethod($target[0], $target[1]);
        }

        foreach ($ref->getParameters() as $p) {
            $type = $p->getType()?->getName();
            if ($type === ServerRequestInterface::class) {
                $args[] = $request;
            } elseif ($type === Response::class) {
                $args[] = new Response();
            } elseif (array_key_exists($p->getName(), $params)) {
                $args[] = $params[$p->getName()];
            } elseif ($p->isOptional()) {
                $args[] = $p->getDefaultValue();
            } else {
                throw new \InvalidArgumentException("Cannot resolve parameter {$p->getName()}");
            }
        }

        // Call and return the result
        return $target instanceof Closure
            ? $target(...$args)
            : $target[0]->{$target[1]}(...$args);
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Utility: replace {key} in URL template
    // ────────────────────────────────────────────────────────────────────────────────

    private static function reconstructUrl(string $routeUrl, array $params): string
    {
        foreach ($params as $k => $v) {
            $routeUrl = str_replace("{{$k}}", $v, $routeUrl);
        }
        return $routeUrl;
    }
}
