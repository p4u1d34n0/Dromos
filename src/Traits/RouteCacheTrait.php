<?php

namespace Dromos\Traits;

use Closure;
use Dromos\Http\Middleware\MiddlewareInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Dromos\Routing\CachedRoutes;
use Dromos\Routing\RouteCacheFile;
use Dromos\Routing\RouteCompiler;

/**
 * Route caches for Router.
 *
 * - loadRoutes() with a cache file dispatches from the cache without
 *   registering routes, while the routes file is unchanged.
 * - enableCache() stores the compiled route table and reuses it while the
 *   registered routes stay the same, so compiling is skipped. Routes are
 *   still registered on every request.
 *
 * Both store a PHP file (see RouteCacheFile).
 */
trait RouteCacheTrait
{
    /** @var bool Whether the route cache is enabled */
    protected static bool $useCache = false;

    protected static ?RouteCacheFile $routeCache = null;

    /**
     * Drop any match table built from the previous route set.
     */
    abstract protected static function forgetMatchTable(): void;

    /**
     * Turn on route caching to the given file. The file is PHP and is run
     * with `require`, so keep it in a directory only the application can write.
     */
    public static function enableCache(string $path): void
    {
        static::$useCache = true;
        static::$routeCache = new RouteCacheFile($path);
        static::forgetMatchTable();
    }

    /**
     * Turn off route caching. The cache file is left in place.
     */
    public static function disableCache(): void
    {
        static::$useCache = false;
        static::$routeCache = null;
        static::forgetMatchTable();
    }

    /**
     * Delete the cache file. It is rebuilt on the next dispatch while caching is enabled.
     */
    public static function clearCache(): void
    {
        static::$routeCache?->clear();
        static::forgetMatchTable();
    }

    /**
     * The controller routes stored in the cache file, keyed by method then
     * route URL. Closure routes are never stored.
     *
     * @return array<string, array<string, array{0:class-string,1:string}>>
     */
    public static function cachedRoutes(): array
    {
        return static::$routeCache?->load()?->handlers ?? [];
    }

    /**
     * Register the routes defined in a PHP file.
     *
     * With a cache file, the first request runs the routes file and stores its
     * routes, compiled, in the cache. Later requests dispatch from the cache
     * without running the routes file, registering or compiling anything,
     * until the routes file changes. While opcache holds both files as
     * current, the routes file isn't checked, so a change counts when PHP
     * would notice changed code (see RouteCacheFile::isPinnedByOpcache()).
     * Only routes to controllers, with
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
        $cache = $cacheFile !== null && self::$routes === [] ? new RouteCacheFile($cacheFile) : null;

        // While opcache holds both files as current, PHP would run the routes
        // file as it was when the cache was built, so it isn't checked
        $signature = $cache?->isPinnedByOpcache($routesFile) ? null : self::signatureOfRoutesFile($routesFile);
        $current = $cache?->current($signature);
        if ($current !== null) {
            self::forgetMatchTable();
            [self::$matchTable, self::$routes, self::$routeMiddleware] = $current;
            if ($signature !== null) {
                $cache->pinWith($routesFile);
            }
            return;
        }
        $signature ??= self::signatureOfRoutesFile($routesFile);

        (static function (string $file): void {
            require $file;
        })($routesFile);

        if ($cache !== null && self::routesAreCacheable()) {
            self::$matchTable = (new RouteCompiler())->compile(self::$routes);
            /** @var array<string, list<class-string<MiddlewareInterface|PsrMiddlewareInterface>>> $middleware checked by routesAreCacheable() */
            $middleware = self::$routeMiddleware;
            $cache->save(new CachedRoutes($signature, self::$matchTable, CachedRoutes::handlersOf(self::$routes), $middleware));
        }
    }

    /**
     * @throws \InvalidArgumentException If the routes file doesn't exist
     */
    private static function signatureOfRoutesFile(string $routesFile): string
    {
        return CachedRoutes::signatureOfFile($routesFile)
            ?? throw new \InvalidArgumentException("Routes file {$routesFile} does not exist.");
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
}
