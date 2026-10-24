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

    /** @var array<string, MiddlewareInterface[]> Per-route middleware keyed by "METHOD:/path" */
    protected static array $routeMiddleware = [];

    /** Compiled from the routes on first dispatch, dropped whenever they change. */
    private static ?CompiledRoutes $matchTable = null;

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
        self::forgetMatchTable();

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
                    $middleware,
                    fn(ServerRequestInterface $r) => self::invoker()->invoke($target, $match->params, $r)
                )->handle($request);
            }

            // No match: 405 if the path exists under other methods, else 404
            $allowedMethods = self::matchTable()->allowedMethods($path, $method);
            if ($allowedMethods !== []) {
                throw RouterException::methodNotAllowed($allowedMethods);
            }

            throw RouterException::routeNotFound($path);
        } catch (RouterException $e) {
            return RouterExceptionHandler::handle($e, $e->getStatusCode(), $e->getArgs());
        } catch (\Throwable $e) {
            return RouterExceptionHandler::handle($e);
        }
    }

    /**
     * The routes being dispatched: the cached routes when the route cache is
     * enabled, otherwise the registered ones.
     *
     * @return array<string, array<string, Closure|array{0:class-string,1:string}>>
     */
    private static function activeRoutes(): array
    {
        return self::$useCache ? self::$compiledRoutes : self::$routes;
    }

    private static function matchTable(): CompiledRoutes
    {
        return self::$matchTable ??= (new RouteCompiler())->compile(self::activeRoutes());
    }

    private static function invoker(): HandlerInvoker
    {
        return self::$invoker ??= new HandlerInvoker(self::$controllerResolver ??= new SharedControllerResolver());
    }
}
