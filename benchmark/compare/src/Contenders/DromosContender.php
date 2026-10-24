<?php

declare(strict_types=1);

namespace Bench\Contenders;

use Bench\Contender;
use Bench\RouteSet;
use Bench\Controllers\DromosController;
use Dromos\Http\Emitter\Emitter;
use Dromos\Http\Request;
use Dromos\Router;

final class DromosContender implements Contender
{
    public function name(): string
    {
        return 'dromos';
    }

    /**
     * Without a cache directory the routes are registered on every boot. With
     * one, they're written to a routes file (as an app keeps them) and loaded
     * with Router::loadRoutes(), so later boots dispatch from the cache.
     */
    public function boot(RouteSet $routes, ?string $cacheDir): void
    {
        Router::reset();
        if ($cacheDir === null) {
            foreach ($routes->routes() as [$method, $path, $action]) {
                Router::{ucfirst(strtolower($method))}($path, [DromosController::class, $action]);
            }
            return;
        }

        $routesFile = "$cacheDir/dromos-routes-source.php";
        if (!is_file($routesFile)) {
            $code = "<?php\n\nuse Dromos\\Router;\nuse " . DromosController::class . ";\n\n";
            foreach ($routes->routes() as [$method, $path, $action]) {
                $code .= sprintf("Router::%s('%s', [DromosController::class, '%s']);\n", ucfirst(strtolower($method)), $path, $action);
            }
            file_put_contents($routesFile, $code);
        }
        Router::loadRoutes($routesFile, "$cacheDir/dromos-routes.php");
    }

    public function handle(string $method, string $path): array
    {
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path];
        $response = (new Router())->handle(new Request());
        return [$response->getStatusCode(), (string) $response->getBody()];
    }

    public function serve(): void
    {
        (new Emitter())->emit((new Router())->handle(new Request()));
    }
}
