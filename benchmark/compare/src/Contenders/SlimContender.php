<?php

declare(strict_types=1);

namespace Bench\Contenders;

use Bench\Contender;
use Bench\RouteSet;
use Bench\Controllers\SlimController;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

/**
 * Slim 4 with slim/psr7, set up as its documentation recommends: routing
 * middleware, then error middleware so a 404 becomes a response. With a
 * cache directory it sets the route collector's cache file, which caches
 * FastRoute's dispatch data. Slim still registers every route on each boot;
 * that's how its cache works.
 */
final class SlimContender implements Contender
{
    private App $app;

    public function name(): string
    {
        return 'slim';
    }

    public function boot(RouteSet $routes, ?string $cacheDir): void
    {
        $this->app = AppFactory::create();
        if ($cacheDir !== null) {
            $this->app->getRouteCollector()->setCacheFile("$cacheDir/slim-routes.php");
        }
        foreach ($routes->routes() as [$method, $path, $action]) {
            $this->app->map([$method], $path, [SlimController::class, $action]);
        }
        $this->app->addRoutingMiddleware();
        $this->app->addErrorMiddleware(false, false, false);
    }

    public function handle(string $method, string $path): array
    {
        $response = $this->app->handle((new ServerRequestFactory())->createServerRequest($method, $path));
        return [$response->getStatusCode(), (string) $response->getBody()];
    }

    public function serve(): void
    {
        $this->app->run();
    }
}
