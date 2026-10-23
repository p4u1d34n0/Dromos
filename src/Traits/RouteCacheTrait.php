<?php

namespace Dromos\Traits;

use Dromos\Routing\RouteCacheFile;

/**
 * Route cache controls for Router.
 *
 * With the cache enabled, the compiled route table is stored in a PHP file
 * (see RouteCacheFile) and reused while the registered routes stay the same,
 * so compiling is skipped. Routes must still be registered on every request.
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
}
