<?php

declare(strict_types=1);

namespace Bench\Matchers;

use Bench\Matcher;
use Bench\RouteSet;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

/** FastRoute's cached dispatcher, as FastRouteContender uses it. */
final class FastRouteMatcher implements Matcher
{
    private Dispatcher $dispatcher;

    public function name(): string
    {
        return 'fastroute';
    }

    public function boot(RouteSet $routes, string $cacheDir): void
    {
        $this->dispatcher = \FastRoute\cachedDispatcher(static function (RouteCollector $r) use ($routes): void {
            foreach ($routes->routes() as [$method, $path, $action]) {
                $r->addRoute($method, $path, $action);
            }
        }, ['cacheFile' => "$cacheDir/fastroute-matcher.php"]);
    }

    public function match(string $method, string $path): array
    {
        $route = $this->dispatcher->dispatch($method, $path);
        return match ($route[0]) {
            Dispatcher::FOUND => [200, $route[1], $route[2]],
            Dispatcher::METHOD_NOT_ALLOWED => [405, null, []],
            default => [404, null, []],
        };
    }
}
