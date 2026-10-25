<?php

/**
 * Runs a sequence of steps against Router::loadRoutes() in one process with
 * opcache on, for RouterLoadRoutesOpcacheTest. Each "request" resets the
 * router, as a new PHP-FPM request would start, and loads the routes.
 *
 * Usage: php opcache-requests.php <autoload> <routes file> <cache file> <step>...
 * Steps: v1, v2 (write the routes file), invalidate-routes, invalidate-cache, request
 * Prints, as JSON, one entry per request.
 */

declare(strict_types=1);

use Dromos\Router;

require $argv[1];
[$routesFile, $cacheFile] = [$argv[2], $argv[3]];

final class OpcacheRequestsController
{
    public function a(): string
    {
        return 'a';
    }

    public function b(): string
    {
        return 'b';
    }
}

$write = static function (string $routes, int $time) use ($routesFile): void {
    file_put_contents($routesFile, "<?php\n\nuse Dromos\\Router;\n\n\$GLOBALS['runs']++;\n" . $routes);
    touch($routesFile, $time);
    clearstatcache();
};

$requests = [];
foreach (array_slice($argv, 4) as $step) {
    match ($step) {
        'v1' => $write("Router::Get('/a', [OpcacheRequestsController::class, 'a']);\n", 1_700_000_000),
        'v2' => $write("Router::Get('/a', [OpcacheRequestsController::class, 'a']);\nRouter::Get('/b', [OpcacheRequestsController::class, 'b']);\n", 1_700_000_100),
        'invalidate-routes' => opcache_invalidate($routesFile, true),
        'invalidate-cache' => opcache_invalidate($cacheFile, true),
        'request' => (static function () use ($routesFile, $cacheFile, &$requests): void {
            $GLOBALS['runs'] = 0;
            Router::reset();
            Router::loadRoutes($routesFile, $cacheFile);
            $requests[] = [
                'a' => Router::match('GET', '/a')[0],
                'b' => Router::match('GET', '/b')[0],
                'ran' => $GLOBALS['runs'],
                'routesInOpcache' => opcache_is_script_cached($routesFile),
            ];
        })(),
    };
}
echo json_encode($requests);
