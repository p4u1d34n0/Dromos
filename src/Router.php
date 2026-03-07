<?php

namespace Dromos;

use Dromos\Env\EnvLoader;

use Closure;
use Dromos\Middleware\RequestParameters;
use Dromos\HTTP\Request;    // implements ServerRequestInterface
use Dromos\HTTP\Response;   // implements ResponseInterface
use Dromos\RouteGroup;
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

    /** @var array<string, MiddlewareInterface[]> Per-route middleware keyed by "METHOD:/path" */
    protected static array $routeMiddleware = [];

    public static function initialize(): void
    {
        $cacheFile = EnvLoader::get('ROUTER_CACHE_FILE');
        if ($cacheFile) {
            self::enableCache($cacheFile);
        }
    }

    /**
     * Reset all static route state. Useful for testing.
     */
    public static function reset(): void
    {
        self::$routes = [];
        self::$missingRoutes = [];
        self::$routeMiddleware = [];
        self::disableCache();
    }

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
    public static function Delete(string $url, $target): string
    {
        return self::addRoute('DELETE', $url, $target);
    }
    public static function Patch(string $url, $target): string
    {
        return self::addRoute('PATCH',  $url, $target);
    }

    public static function Resource(string $url, string $controller): RouteResource
    {
        $resource = new RouteResource($url, $controller);
        return $resource;
    }

    /**
     * Create a route group with a shared URL prefix.
     *
     * @param string   $prefix   URL prefix for all routes in the group
     * @param callable $callback Receives a RouteGroup instance for route registration
     * @return RouteGroup
     */
    public static function group(string $prefix, callable $callback): RouteGroup
    {
        $group = new RouteGroup($prefix);
        $callback($group);
        return $group;
    }

    /**
     * Register middleware for a specific route.
     *
     * @param string              $method     HTTP method
     * @param string              $url        Route URL path
     * @param MiddlewareInterface[] $middleware Array of middleware instances
     */
    public static function setRouteMiddleware(string $method, string $url, array $middleware): void
    {
        $key = strtoupper($method) . ':' . $url;
        if (!isset(self::$routeMiddleware[$key])) {
            self::$routeMiddleware[$key] = [];
        }
        self::$routeMiddleware[$key] = array_merge(self::$routeMiddleware[$key], $middleware);
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
            $path = $request->getUri()->getPath();

            // Determine the routes to use (cached or standard)
            $routes = self::$useCache ? self::$compiledRoutes : self::$routes;

            if (!isset($routes[$method])) {
                // Get available methods for this route
                $available = array_keys($routes);
                throw RouterException::methodNotAllowed($available);
            }

            foreach ($routes[$method] as $routeUrl => $target) {
                // Exact match
                if ($routeUrl === $path) {
                    $routeKey = $method . ':' . $routeUrl;
                    if (isset(self::$routeMiddleware[$routeKey])) {
                        return $this->runRouteMiddleware(
                            self::$routeMiddleware[$routeKey],
                            $request,
                            $target,
                            []
                        );
                    }
                    return $this->invokeTarget($target, [], $request);
                }

                // Parameterized match
                $params = $this->extractParams($routeUrl, $path);
                if (!empty($params) && self::reconstructUrl($routeUrl, $params) === $path) {
                    $routeKey = $method . ':' . $routeUrl;
                    if (isset(self::$routeMiddleware[$routeKey])) {
                        return $this->runRouteMiddleware(
                            self::$routeMiddleware[$routeKey],
                            $request,
                            $target,
                            $params
                        );
                    }
                    return $this->invokeTarget($target, $params, $request);
                }
            }

            throw RouterException::routeNotFound($path);
        } catch (RouterException $e) {
            // Handle router-specific exceptions with the correct status code and args
            return RouterExceptionHandler::handle($e, $e->getStatusCode(), $e->getArgs());
        } catch (\Throwable $e) {
            // Handle any other exceptions with a generic 500 error
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

        // Split route into static segments and placeholders, escape static parts
        $parts = preg_split('/(\{[^}]+\})/', $route, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = '';
        foreach ($parts as $part) {
            if (preg_match('/^\{[^}]+\}$/', $part)) {
                $pattern .= '([^/]+)';
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        if (! preg_match("#^{$pattern}$#", $path, $values)) {
            return [];
        }

        array_shift($values); // drop full match
        return array_combine($names, $values);
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Per-route middleware pipeline
    // ────────────────────────────────────────────────────────────────────────────────

    /**
     * Execute per-route middleware before invoking the route target.
     *
     * Builds a PSR-15 handler chain: each middleware wraps the next, with the
     * final handler calling invokeTarget on the matched route.
     *
     * @param MiddlewareInterface[]   $middlewareList
     * @param ServerRequestInterface  $request
     * @param mixed                   $target
     * @param array                   $params
     * @return ResponseInterface
     */
    private function runRouteMiddleware(
        array $middlewareList,
        ServerRequestInterface $request,
        $target,
        array $params
    ): ResponseInterface {
        // Final handler invokes the route target with params
        $invoker = fn(ServerRequestInterface $req) => $this->invokeTarget($target, $params, $req);
        $finalHandler = new class($invoker) implements RequestHandlerInterface {
            private \Closure $invoker;

            public function __construct(\Closure $invoker)
            {
                $this->invoker = $invoker;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->invoker)($request);
            }
        };

        // Wrap middleware around the final handler (reverse order)
        $handler = $finalHandler;
        for ($i = count($middlewareList) - 1; $i >= 0; $i--) {
            $mw   = $middlewareList[$i];
            $next = $handler;
            $handler = new class($mw, $next) implements RequestHandlerInterface {
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

        return $handler->handle($request);
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
            if ($type !== null && is_a($type, ServerRequestInterface::class, true)) {
                $args[] = $request;
            } elseif ($type !== null && is_a($type, ResponseInterface::class, true)) {
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
