<?php

namespace Dromos;

use Closure;
use Dromos\Env\EnvLoader;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Routing\CompiledRoutes;
use Dromos\Routing\ControllerResolverInterface;
use Dromos\Routing\HandlerInvoker;
use Dromos\Routing\MiddlewarePipeline;
use Dromos\Routing\RouteCompiler;
use Dromos\Routing\SharedControllerResolver;
use Dromos\Traits\RouteCacheTrait;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;

/**
 * Router for Dromos: a request handler that PSR-15 code can use through
 * PsrRequestHandler.
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

    /** @var list<MiddlewareInterface|PsrMiddlewareInterface> */
    private array $middlewareStack = [];

    /** @var array<string, list<MiddlewareInterface|PsrMiddlewareInterface|class-string<MiddlewareInterface|PsrMiddlewareInterface>>> Per-route middleware keyed by "METHOD:/path" */
    protected static array $routeMiddleware = [];


    /** Compiled from the routes on first dispatch, dropped whenever they change. */
    private static ?CompiledRoutes $matchTable = null;

    /**
     * Controller routes read from the cache file when none are registered
     * (deprecated, see matchTable()). Null when the registered routes are used.
     *
     * @var array<string, array<string, array{0:class-string,1:string}>>|null
     */
    private static ?array $routesFromCache = null;

    /**
     * match()'s result for each method and path that matched a route without
     * placeholders. It can't change until the routes or middleware do, and
     * there is at most one entry per route, so it's kept until then.
     *
     * @var array<string, array<string, array{0:200, 1:Closure|array{0:class-string,1:string}, 2:array{}, 3:list<MiddlewareInterface|PsrMiddlewareInterface|class-string<MiddlewareInterface|PsrMiddlewareInterface>>}>>
     */
    private static array $staticResults = [];

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
        self::$staticResults = [];
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
     * Middleware can be an instance, or a class name that the controller
     * resolver builds (once per class) when a request reaches the route.
     * Routes whose middleware are all class names can be cached by
     * loadRoutes().
     *
     * @param string $method HTTP method
     * @param string $url    Route URL path
     * @param list<MiddlewareInterface|PsrMiddlewareInterface|class-string<MiddlewareInterface|PsrMiddlewareInterface>> $middleware
     * @throws \InvalidArgumentException If an entry is not middleware
     */
    public static function setRouteMiddleware(string $method, string $url, array $middleware): void
    {
        foreach ($middleware as $entry) {
            MiddlewarePipeline::check($entry);
        }
        $key = strtoupper($method) . ':' . $url;
        self::$routeMiddleware[$key] = array_merge(self::$routeMiddleware[$key] ?? [], $middleware);
        self::$staticResults = [];
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
        if (is_array($target) && count($target) === 2 && is_string($target[0]) && is_string($target[1])) {
            // The controller class isn't loaded here: registering a route must not
            // load every controller's file. The resolver loads and checks it when
            // a request first reaches the route.
            return [$target[0], $target[1]];
        }
        throw new \InvalidArgumentException("Invalid route target for URL.");
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Middleware registration (instance)
    // ────────────────────────────────────────────────────────────────────────────────

    public function addMiddleware(MiddlewareInterface|PsrMiddlewareInterface $middleware): void
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
            $path = $request->getUri()->getPath();
            $route = self::match($request->getMethod(), $path);
            if ($route[0] === 200) {
                [, $handler, $params, $middleware] = $route;
                if ($middleware === []) {
                    return self::invoker()->invoke($handler, $params, $request);
                }
                return MiddlewarePipeline::wrap(
                    $middleware,
                    fn(ServerRequestInterface $r) => self::invoker()->invoke($handler, $params, $r),
                    self::resolver()
                )->handle($request);
            }

            // No match: these are the client's errors, so they're answered without being logged
            $error = $route[0] === 405
                ? RouterException::methodNotAllowed($route[1])
                : RouterException::routeNotFound($path);
            return RouterExceptionHandler::render($error, $error->getStatusCode(), $error->getArgs());
        } catch (RouterException $e) {
            return RouterExceptionHandler::handle($e, $e->getStatusCode(), $e->getArgs());
        } catch (\Throwable $e) {
            return RouterExceptionHandler::handle($e);
        }
    }

    /**
     * Lean mode: find the route for a method and path without creating a
     * request or response, running middleware or calling the handler. The
     * caller does those itself, as with a matcher-only router such as
     * FastRoute, whose result shape this follows:
     *
     * - [200, $handler, $params, $middleware] when a route matches
     * - [404] when no route matches the path
     * - [405, $allowedMethods] when routes match the path only under other methods
     *
     * It uses the same routes and route cache as handle(). The route's own
     * middleware is returned, not run: run it yourself if you rely on it.
     * Middleware added with addMiddleware() belongs to a Router instance and
     * isn't seen here.
     *
     * @param string $path The URL path only, without the query string
     * @return array{0:200, 1:Closure|array{0:class-string,1:string}, 2:array<string, string>, 3:list<MiddlewareInterface|PsrMiddlewareInterface|class-string<MiddlewareInterface|PsrMiddlewareInterface>>}|array{0:404}|array{0:405, 1:string[]}
     */
    public static function match(string $method, string $path): array
    {
        if (isset(self::$staticResults[$method][$path])) {
            return self::$staticResults[$method][$path];
        }

        // Inlined lookups (matchTable(), activeRoutes()): this runs on every request
        $table = self::$matchTable ?? self::matchTable();
        $match = $table->find($method, $path);
        if ($match !== null) {
            [$url, $params] = $match;
            $found = [
                200,
                (self::$routesFromCache ?? self::$routes)[$method][$url],
                $params,
                self::$routeMiddleware === [] ? [] : self::$routeMiddleware[$method . ':' . $url] ?? [],
            ];
            if ($params === []) {
                self::$staticResults[$method][$path] = $found;
            }
            return $found;
        }

        $allowedMethods = $table->allowedMethods($path, $method);
        return $allowedMethods === [] ? [404] : [405, $allowedMethods];
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
}
