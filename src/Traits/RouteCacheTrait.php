<?php

namespace Dromos\Traits;

trait RouteCacheTrait
{
    /** @var bool */
    protected static bool $useCache = false;

    /** @var string|null */
    protected static ?string $cacheFile = null;

    /** @var array<int,array{method:string,path:string,handler:mixed}> */
    protected static array $compiledRoutes = [];

    /**
     * Turn on route caching to the given file.
     */
    public static function enableCache(string $path): void
    {
        static::$useCache  = true;
        static::$cacheFile = $path;

        if (file_exists($path)) {
            $data   = @file_get_contents($path);
            $parsed = $data !== false ? @unserialize($data) : [];
            static::$compiledRoutes = is_array($parsed) ? $parsed : [];
        }
    }

    /**
     * Turn off route caching.
     */
    public static function disableCache(): void
    {
        static::$useCache        = false;
        static::$cacheFile       = null;
        static::$compiledRoutes  = [];
    }

    /**
     * Delete the cache file and reset in-memory cache.
     */
    public static function clearCache(): void
    {
        if (static::$cacheFile && file_exists(static::$cacheFile)) {
            @unlink(static::$cacheFile);
        }
        static::$compiledRoutes = [];
    }

    /**
     * Return the currently cached route definitions.
     *
     * @return array<int,array{method:string,path:string,handler:mixed}>
     */
    public static function cachedRoutes(): array
    {
        return static::$compiledRoutes;
    }

    /**
     * If caching is enabled, immediately write updated routes back to disk.
     */
    protected static function writeCacheFile(): void
    {
        if (! static::$useCache || ! static::$cacheFile) {
            return;
        }

        file_put_contents(
            static::$cacheFile,
            serialize(static::$compiledRoutes),
            LOCK_EX
        );
    }

    /**
     * Hook this after you add new routes to keep cache in sync.
     *
     * @param array{method:string,path:string,handler:mixed} $route
     */
    protected static function trackRouteForCache(array $route): void
    {
        if (static::$useCache) {
            static::$compiledRoutes[] = $route;
            static::writeCacheFile();
        }
    }
}
