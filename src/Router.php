<?php

namespace Dromos;

use Closure;
use Dromos\Env\EnvLoader;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Routing\CachedRoutes;
use Dromos\Routing\CompiledRoutes;
use Dromos\Routing\ControllerResolverInterface;
use Dromos\Routing\HandlerInvoker;
use Dromos\Routing\MiddlewarePipeline;
use Dromos\Routing\RouteCacheFile;
use Dromos\Routing\RouteCompiler;
use Dromos\Routing\SharedControllerResolver;
use Dromos\Traits\RouteCacheTrait;

/**
 * PSR-15–style Router for Dromos.
 *
 * Holds the registered routes and middleware, and dispatches requests:
 * RouteCompiler and CompiledRoutes find the route, MiddlewarePipeline runs
 * the middleware, and HandlerInvoker calls the handler.
 */
class Router implements RequestHandlerInterface
{
    use RouteCacheTrait;

    /** @var array<string, array<string, Closure|array{0:class-string,1:string}>> Handlers keyed by method, then route URL */
    protected static array $routes = [];

    /** @var array<int, array{0:string,1:string}> [controller class, missing method] */
    private static array $missingRoutes = [];

    /** @var MiddlewareInterface[] */
    private array $middlewareStack = [];

    /** @var array<string, list<MiddlewareInterface|class-string<MiddlewareInterface>>> Per-route middleware keyed by "METHOD:/path" */
    protected static array $routeMiddleware = [];

    /** @var array<string, true> Middleware class names already checked */
    private static array $checkedMiddleware = [];

    /** Compiled from the routes on first dispatch, dropped whenever they change. */
    private static ?CompiledRoutes $matchTable = null;

    /**
     * Controller routes read from the cache file when none are registered
     * (deprecated, see matchTable()). Null when the registered routes are used.
     *
     * @var array<string, array<string, array{0:class-string,1:string}>>|null
     */
    private static ?array $routesFromCache = null;

    private static ?ControllerResolverInterface $controllerResolver = null;

    private static ?HandlerInvoker $invoker = null;

    public static function initialize(): void
    {
        $cacheFile = EnvLoader::get('ROUTER_CACHE_FILE');
        if ($cacheFile) {
            self::enableCache($cacheFile);
        }
    }

    /**
     * Reset all static router state, including controller instances and any
     * custom controller resolver. Useful for testing.
     */
    public static function reset(): void
    {
        self::$routes = [];
        self::$missingRoutes = [];
        self::$routeMiddleware = [];
        self::disableCache();
        self::forgetMatchTable();
        self::$controllerResolver = null;
        self::$invoker = null;
    }

    /**
     * Use a custom resolver, such as a DI container adapter, to create
     * controllers. The default creates one instance per class.
     */
    public static function setControllerResolver(ControllerResolverInterface $resolver): void
    {
        self::$controllerResolver = $resolver;
        self::$invoker = null;
    }

    protected static function forgetMatchTable(): void
    {
        self::$matchTable = null;
        self::$routesFromCache = null;
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

    /**
     * Register the routes defined in a PHP file.
     *
     * With a cache file, the first request runs the routes file and stores its
     * routes, compiled, in the cache. Later requests dispatch from the cache
     * without running the routes file, registering or compiling anything,
     * until the routes file changes. Only routes to controllers, with
     * middleware given by class name, can be stored; if the file defines any
     * others, it simply runs on every request. Routes registered before this call also
     * mean the file runs every time, so their order is kept.
     *
     * The cache file is run with `require`: keep it in a directory only the
     * application can write to.
     *
     * @throws \InvalidArgumentException If the routes file doesn't exist
     */
    public static function loadRoutes(string $routesFile, ?string $cacheFile = null): void
    {
        $signature = CachedRoutes::signatureOfFile($routesFile)
            ?? throw new \InvalidArgumentException("Routes file {$routesFile} does not exist.");
        $cache = $cacheFile !== null && self::$routes === [] ? new RouteCacheFile($cacheFile) : null;

        $cached = $cache?->load();
        if ($cached !== null && $cached->signature === $signature) {
            self::$routes = $cached->handlers;
            self::$routeMiddleware = $cached->middleware;
            self::$matchTable = $cached->table;
            return;
        }

        (static function (string $file): void {
            require $file;
        })($routesFile);

        if ($cache !== null && self::routesAreCacheable()) {
            self::$matchTable = (new RouteCompiler())->compile(self::$routes);
            /** @var array<string, list<class-string<MiddlewareInterface>>> $middleware checked by routesAreCacheable() */
            $middleware = self::$routeMiddleware;
            $cache->save(new CachedRoutes($signature, self::$matchTable, CachedRoutes::handlersOf(self::$routes), $middleware));
        }
    }

    /**
     * True if every route is a controller route whose middleware are class
     * names, so the routes can be stored and dispatched from a cache file.
     */
    private static function routesAreCacheable(): bool
    {
        foreach (self::$routeMiddleware as $middleware) {
            foreach ($middleware as $entry) {
                if (!is_string($entry)) {
                    return false;
                }
            }
        }
        foreach (self::$routes as $routes) {
            foreach ($routes as $handler) {
                if ($handler instanceof Closure) {
                    return false;
                }
            }
        }
        return true;
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
     * Middleware can be an instance, or a class name that the controller
     * resolver builds (once per class) when a request reaches the route.
     * Routes whose middleware are all class names can be cached by
     * loadRoutes().
     *
     * @param string $method HTTP method
     * @param string $url    Route URL path
     * @param list<MiddlewareInterface|class-string<MiddlewareInterface>> $middleware
     * @throws \InvalidArgumentException If an entry is not middleware
     */
    public static function setRouteMiddleware(string $method, string $url, array $middleware): void
    {
        foreach ($middleware as $entry) {
            self::checkMiddleware($entry);
        }
        $key = strtoupper($method) . ':' . $url;
        self::$routeMiddleware[$key] = array_merge(self::$routeMiddleware[$key] ?? [], $middleware);
    }

    /**
     * @throws \InvalidArgumentException If $middleware is neither a MiddlewareInterface
     *                                   nor the name of a class implementing it
     */
    private static function checkMiddleware(mixed $middleware): void
    {
        if ($middleware instanceof MiddlewareInterface || (is_string($middleware) && isset(self::$checkedMiddleware[$middleware]))) {
            return;
        }
        if (!is_string($middleware) || !is_subclass_of($middleware, MiddlewareInterface::class)) {
            throw new \InvalidArgumentException(
                'Route middleware must implement ' . MiddlewareInterface::class . ', got '
                . (is_string($middleware) ? $middleware : get_debug_type($middleware)) . '.'
            );
        }
        self::$checkedMiddleware[$middleware] = true;
    }

    protected static function addRoute(string $method, string $url, $target): string
    {
        self::$routes[$method][$url] = self::checkTarget($target);
        self::forgetMatchTable();

        return self::class;
    }

    /** @return Closure|array */
    protected static function checkTarget($target)
    {
        if ($target instanceof Closure) {
            return $target;
        }
        if (is_array($target) && count($target) === 2 && is_string($target[0])) {
            // Controllers are created on first dispatch by the controller resolver, not here
            [$class, $method] = $target;
            if (!class_exists($class)) {
                throw new \InvalidArgumentException("Route target class {$class} does not exist.");
            }
            if (!method_exists($class, $method)) {
                self::$missingRoutes[] = [$class, $method];
            }
            return [$class, $method];
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
        if ($this->middlewareStack === []) {
            return $this->dispatch($request);
        }
        return MiddlewarePipeline::wrap($this->middlewareStack, fn(ServerRequestInterface $r) => $this->dispatch($r))
            ->handle($request);
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Dispatch
    // ────────────────────────────────────────────────────────────────────────────────

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $method = $request->getMethod();
            $path = $request->getUri()->getPath();

            $match = self::matchTable()->match($method, $path);
            if ($match !== null) {
                $target = self::activeRoutes()[$method][$match->url];
                $middleware = self::$routeMiddleware[$method . ':' . $match->url] ?? [];
                if ($middleware === []) {
                    return self::invoker()->invoke($target, $match->params, $request);
                }
                return MiddlewarePipeline::wrap(
                    array_map(self::middlewareInstance(...), $middleware),
                    fn(ServerRequestInterface $r) => self::invoker()->invoke($target, $match->params, $r)
                )->handle($request);
            }

            // No match: 405 if the path exists under other methods, else 404. These
            // are the client's errors, so they're answered without being logged
            $allowedMethods = self::matchTable()->allowedMethods($path, $method);
            $error = $allowedMethods !== []
                ? RouterException::methodNotAllowed($allowedMethods)
                : RouterException::routeNotFound($path);
            return RouterExceptionHandler::render($error, $error->getStatusCode(), $error->getArgs());
        } catch (RouterException $e) {
            return RouterExceptionHandler::handle($e, $e->getStatusCode(), $e->getArgs());
        } catch (\Throwable $e) {
            return RouterExceptionHandler::handle($e);
        }
    }

    /**
     * The routes being dispatched: the registered routes, or in the deprecated
     * cache-only mode, the controller routes read from the cache file.
     *
     * @return array<string, array<string, Closure|array{0:class-string,1:string}>>
     */
    private static function activeRoutes(): array
    {
        return self::$routesFromCache ?? self::$routes;
    }

    private static function matchTable(): CompiledRoutes
    {
        if (self::$matchTable !== null) {
            return self::$matchTable;
        }

        $compiler = new RouteCompiler();
        if (self::$routeCache === null) {
            return self::$matchTable = $compiler->compile(self::$routes);
        }

        if (self::$routes === []) {
            $cachedRoutes = self::cachedRoutes();
            if ($cachedRoutes !== []) {
                @trigger_error(
                    'Dispatching routes from the route cache without registering them is deprecated since Dromos 1.2 '
                    . 'and will stop working in 2.0. Register your routes on every request; the cache still skips compiling them.',
                    E_USER_DEPRECATED
                );
                self::$routesFromCache = $cachedRoutes;
                return self::$matchTable = $compiler->compile($cachedRoutes);
            }
        }

        return self::$matchTable = self::$routeCache->remember(self::$routes, $compiler->compile(...));
    }

    private static function invoker(): HandlerInvoker
    {
        return self::$invoker ??= new HandlerInvoker(self::resolver());
    }

    private static function resolver(): ControllerResolverInterface
    {
        return self::$controllerResolver ??= new SharedControllerResolver();
    }

    /**
     * @param MiddlewareInterface|class-string<MiddlewareInterface> $middleware
     */
    private static function middlewareInstance(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }
        $instance = self::resolver()->resolve($middleware);
        return $instance instanceof MiddlewareInterface
            ? $instance
            : throw new \UnexpectedValueException("The resolver returned a " . get_debug_type($instance) . " for middleware {$middleware}.");
    }
}
