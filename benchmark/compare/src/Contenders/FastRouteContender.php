<?php

declare(strict_types=1);

namespace Bench\Contenders;

use Bench\Contender;
use Bench\RouteSet;
use Bench\Controllers\MatcherController;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

/**
 * nikic/fast-route is a matcher only: there is no request, response or
 * handler calling, so this adapter adds the least glue a front controller
 * needs (create the controller, pass the params, return JSON). That makes it
 * a lower bound rather than a like-for-like router. With a cache directory
 * it uses cachedDispatcher(), which skips route registration once cached.
 */
final class FastRouteContender implements Contender
{
    private Dispatcher $dispatcher;

    public function name(): string
    {
        return 'fastroute';
    }

    public function boot(RouteSet $routes, ?string $cacheDir): void
    {
        $define = static function (RouteCollector $r) use ($routes): void {
            foreach ($routes->routes() as [$method, $path, $action]) {
                $r->addRoute($method, $path, [MatcherController::class, $action]);
            }
        };
        $this->dispatcher = $cacheDir === null
            ? \FastRoute\simpleDispatcher($define)
            : \FastRoute\cachedDispatcher($define, ['cacheFile' => "$cacheDir/fastroute-routes.php"]);
    }

    public function handle(string $method, string $path): array
    {
        $route = $this->dispatcher->dispatch($method, rawurldecode($path));
        return match ($route[0]) {
            Dispatcher::FOUND => [200, (new $route[1][0]())->{$route[1][1]}(...$route[2])],
            Dispatcher::METHOD_NOT_ALLOWED => [405, ''],
            default => [404, ''],
        };
    }

    public function serve(): void
    {
        [$status, $body] = $this->handle($_SERVER['REQUEST_METHOD'], (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
        http_response_code($status);
        header('Content-Type: application/json');
        echo $body;
    }
}
