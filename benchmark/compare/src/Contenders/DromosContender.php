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

    public function boot(RouteSet $routes, ?string $cacheDir): void
    {
        Router::reset();
        foreach ($routes->routes() as [$method, $path, $action]) {
            Router::{ucfirst(strtolower($method))}($path, [DromosController::class, $action]);
        }
        if ($cacheDir !== null) {
            Router::enableCache("$cacheDir/dromos-routes.php");
        }
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
