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

    /** Parameterised routes per combined regex, keeping each pattern well inside PCRE's size limit. */
    private const ROUTES_PER_REGEX = 50;

    /** @var array<int,array{method:string,path:string,handler:mixed}> */
    protected static array $routes = [];

    /** @var array<int, array{0:string,1:string}> [controller class, missing method] */
    private static array $missingRoutes = [];

    /** @var MiddlewareInterface[] */
    private array $middlewareStack = [];

    /** @var array<string, MiddlewareInterface[]> Per-route middleware keyed by "METHOD:/path" */
    protected static array $routeMiddleware = [];

    /**
     * Route table compiled for matching. Built on first dispatch and dropped
     * whenever the routes change. See compileRoutes().
     *
     * @var array<string, array{exact: array<string, array{0:mixed,1:int}>, regexes: string[], parameterised: array<int, array{0:string,1:mixed,2:string[],3:int}>, shadowedBy: array<string, array|false>}>|null
     */
    private static ?array $matchTable = null;

    /** Where a handler parameter's value comes from. */
    private const ARG_REQUEST = 0;
    private const ARG_RESPONSE = 1;
    private const ARG_NAMED = 2;

    /**
     * Argument plans for closure handlers. Entries go when their closure is freed.
     *
     * @var \WeakMap<Closure, array>|null
     */
    private static ?\WeakMap $closurePlans = null;

    /** @var array<string, array> Argument plans for controller methods, keyed by "Class::method" */
    private static array $methodPlans = [];

    /** @var array<string, object> Controller instances keyed by lower-case class name */
    private static array $controllers = [];

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
        self::forgetMatchTable();
        self::$closurePlans = null;
        self::$methodPlans = [];
        self::$controllers = [];
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
            // Controllers are created on first use (see controller()), not at registration
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

            $match = self::matchRoute($method, $path);
            if ($match !== null) {
                [$routeUrl, $target, $params] = $match;
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

            // No match found — check if path exists under other methods (405 vs 404)
            $allowedMethods = [];
            foreach (array_keys(self::$matchTable) as $otherMethod) {
                if ($otherMethod !== $method && self::matchRoute($otherMethod, $path) !== null) {
                    $allowedMethods[] = $otherMethod;
                }
            }

            if (!empty($allowedMethods)) {
                throw RouterException::methodNotAllowed($allowedMethods);
            }

            throw RouterException::routeNotFound($path);
        } catch (RouterException $e) {
            return RouterExceptionHandler::handle($e, $e->getStatusCode(), $e->getArgs());
        } catch (\Throwable $e) {
            return RouterExceptionHandler::handle($e);
        }
    }


    // ────────────────────────────────────────────────────────────────────────────────
    // Route matching against the compiled table
    // ────────────────────────────────────────────────────────────────────────────────

    /**
     * Find the route for a method and path.
     *
     * Routes are tried in registration order and the first match wins, exactly
     * as a linear scan would: a route matches when its URL equals the path, or
     * when its {placeholders} match the path's segments.
     *
     * @return array{0:string,1:mixed,2:array<string,string>}|null [route URL, target, params]
     */
    private static function matchRoute(string $method, string $path): ?array
    {
        self::$matchTable ??= self::compileRoutes();
        $table = self::$matchTable[$method] ?? null;
        if ($table === null) {
            return null;
        }

        if (isset($table['exact'][$path])) {
            [$target, $position] = $table['exact'][$path];
            // A parameterised route registered earlier that also matches this path wins.
            // Looked up on first hit and remembered, so building the table stays cheap.
            $earlier = self::$matchTable[$method]['shadowedBy'][$path] ??= self::matchParameterised($table, $path) ?? false;
            if ($earlier === false || $earlier[3] >= $position) {
                return [$path, $target, []];
            }
            return [$earlier[0], $earlier[1], $earlier[2]];
        }

        $match = self::matchParameterised($table, $path);
        return $match === null ? null : [$match[0], $match[1], $match[2]];
    }

    /**
     * Match the path against the method's parameterised routes.
     *
     * @return array{0:string,1:mixed,2:array<string,string>,3:int}|null [route URL, target, params, position]
     */
    private static function matchParameterised(array $table, string $path): ?array
    {
        foreach ($table['regexes'] as $regex) {
            if (preg_match($regex, $path, $m)) {
                [$routeUrl, $target, $names, $position] = $table['parameterised'][(int) $m['MARK']];
                return [$routeUrl, $target, array_combine($names, array_slice($m, 1, count($names))), $position];
            }
        }
        return null;
    }

    /**
     * Build the per-method match table from the registered (or cached) routes.
     *
     * Every route URL goes in a hash map for exact matches. Routes with
     * {placeholders} are also compiled into combined regexes, in registration
     * order, where (*MARK) identifies the route that matched.
     */
    private static function compileRoutes(): array
    {
        $routes = self::$useCache ? self::$compiledRoutes : self::$routes;
        $table = [];

        foreach ($routes as $method => $list) {
            $exact = [];
            $parameterised = [];
            $patterns = [];
            $position = 0;

            foreach ($list as $routeUrl => $target) {
                $routeUrl = (string) $routeUrl;
                $exact[$routeUrl] = [$target, ++$position];
                if (!str_contains($routeUrl, '{')) {
                    continue;
                }

                // Odd entries are the {placeholders}, even entries the literal text between them
                $parts = preg_split('/(\{[^}]+\})/', $routeUrl, -1, PREG_SPLIT_DELIM_CAPTURE);
                if (count($parts) === 1) {
                    continue;
                }

                $pattern = '';
                $groups = [];
                foreach ($parts as $i => $part) {
                    if ($i % 2 === 0) {
                        $pattern .= preg_quote($part, '~');
                        continue;
                    }
                    $name = substr($part, 1, -1);
                    if (isset($groups[$name])) {
                        // A repeated placeholder must capture the same value each time
                        $pattern .= '\\g{' . $groups[$name] . '}';
                        continue;
                    }
                    $groups[$name] = count($groups) + 1;
                    $pattern .= '([^/]+)';
                }

                $index = count($parameterised);
                $parameterised[$index] = [$routeUrl, $target, array_keys($groups), $position];
                $patterns[] = $pattern . '(*MARK:' . $index . ')';
            }

            // (?| ... ) restarts group numbering in each branch, so params are always $m[1..n]
            $regexes = [];
            foreach (array_chunk($patterns, self::ROUTES_PER_REGEX) as $chunk) {
                $regexes[] = '~^(?|' . implode('|', $chunk) . ')$~';
            }

            $table[$method] = [
                'exact'         => $exact,
                'regexes'       => $regexes,
                'parameterised' => $parameterised,
                'shadowedBy'    => [],
            ];
        }

        return $table;
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

    /**
     * The controller instance for a class, created the first time one of its
     * routes is dispatched and reused after that (one per class per process).
     */
    private static function controller(string $class): object
    {
        return self::$controllers[strtolower(ltrim($class, '\\'))] ??= new $class();
    }

    /**
     * How to fill each of a handler's parameters, worked out by reflection the
     * first time the handler runs and reused after that.
     *
     * @param Closure|array{0:object,1:string} $target
     * @return array<int, array{0:int,1:string,2:\ReflectionParameter}> [source, name, parameter]
     */
    private static function argumentPlan($target): array
    {
        if ($target instanceof Closure) {
            self::$closurePlans ??= new \WeakMap();
            return self::$closurePlans[$target] ??= self::buildArgumentPlan(new \ReflectionFunction($target));
        }

        $key = get_class($target[0]) . '::' . $target[1];
        return self::$methodPlans[$key] ??= self::buildArgumentPlan(new \ReflectionMethod($target[0], $target[1]));
    }

    /** @return array<int, array{0:int,1:string,2:\ReflectionParameter}> */
    private static function buildArgumentPlan(\ReflectionFunctionAbstract $ref): array
    {
        $plan = [];
        foreach ($ref->getParameters() as $p) {
            $type = $p->getType()?->getName();
            $source = match (true) {
                $type !== null && is_a($type, ServerRequestInterface::class, true) => self::ARG_REQUEST,
                $type !== null && is_a($type, ResponseInterface::class, true) => self::ARG_RESPONSE,
                default => self::ARG_NAMED,
            };
            $plan[] = [$source, $p->getName(), $p];
        }
        return $plan;
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

        if (is_array($target) && is_string($target[0])) {
            $target = [self::controller($target[0]), $target[1]];
        }

        // Resolve each handler parameter using a plan worked out once per handler
        $args = [];
        foreach (self::argumentPlan($target) as [$source, $name, $parameter]) {
            $args[] = match ($source) {
                self::ARG_REQUEST  => $request,
                self::ARG_RESPONSE => new Response(),
                default => array_key_exists($name, $params)
                    ? $params[$name]
                    : ($parameter->isOptional()
                        ? $parameter->getDefaultValue()
                        : throw new \InvalidArgumentException("Cannot resolve parameter {$name}")),
            };
        }

        // Call and return the result
        return $target instanceof Closure
            ? $target(...$args)
            : $target[0]->{$target[1]}(...$args);
    }
}
