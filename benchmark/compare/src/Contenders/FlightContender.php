<?php

declare(strict_types=1);

namespace Bench\Contenders;

use Bench\Contender;
use Bench\RouteSet;
use Bench\Controllers\FlightController;
use flight\Engine;

/**
 * Flight has no route cache, so a cache directory is ignored. Flight sends
 * its response itself, so handle() captures the output.
 */
final class FlightContender implements Contender
{
    private Engine $app;

    public function name(): string
    {
        return 'flight';
    }

    public function boot(RouteSet $routes, ?string $cacheDir): void
    {
        $this->app = new Engine();
        foreach ($routes->routes() as [$method, $path, $action]) {
            $this->app->route(
                $method . ' ' . RouteSet::rewrite($path, fn(string $name) => "@$name"),
                [FlightController::class, $action]
            );
        }
    }

    public function handle(string $method, string $path): array
    {
        $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path, 'SCRIPT_NAME' => '/index.php'];
        ob_start();
        $this->app->start();
        $body = (string) ob_get_clean();
        // start() installs Flight's error handlers on every call; remove them so
        // they don't pile up across requests or swallow the benchmark's own errors
        restore_error_handler();
        restore_exception_handler();
        return [$this->app->response()->status(), $body];
    }

    public function serve(): void
    {
        $this->app->start();
    }
}
