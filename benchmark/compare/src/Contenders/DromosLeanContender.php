<?php

declare(strict_types=1);

namespace Bench\Contenders;

use Bench\Contender;
use Bench\RouteSet;
use Bench\Controllers\MatcherController;
use Dromos\Router;

/**
 * Dromos in lean mode: Router::match() finds the route and the adapter
 * calls the handler, with no request, response or middleware. It's wired
 * like the FastRoute and Symfony adapters, with the same glue and the same
 * controller, so the three compare like for like. The routes and route
 * cache are the same as DromosContender's.
 */
final class DromosLeanContender implements Contender
{
    public function name(): string
    {
        return 'dromos-lean';
    }

    public function boot(RouteSet $routes, ?string $cacheDir): void
    {
        DromosContender::register($routes, $cacheDir, MatcherController::class);
    }

    public function handle(string $method, string $path): array
    {
        $route = Router::match($method, $path);
        return match ($route[0]) {
            200 => [200, (new $route[1][0]())->{$route[1][1]}(...$route[2])],
            405 => [405, ''],
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
