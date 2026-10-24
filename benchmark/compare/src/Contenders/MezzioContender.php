<?php

declare(strict_types=1);

namespace Bench\Contenders;

use Bench\Contender;
use Bench\RouteSet;
use Bench\Controllers\Mezzio;
use Laminas\ConfigAggregator\ArrayProvider;
use Laminas\ConfigAggregator\ConfigAggregator;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stratigility\Middleware\ErrorHandler;
use Mezzio\Application;
use Mezzio\Handler\NotFoundHandler;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;

/**
 * Mezzio 3 with FastRoute, laminas-diactoros and laminas-servicemanager,
 * wired as the Mezzio skeleton app is: config providers merged by
 * ConfigAggregator, a service container built from that config, the
 * skeleton's middleware pipeline, and one PSR-15 handler per action. With a
 * cache directory it caches the merged config and FastRoute's dispatch data,
 * as the skeleton does in production. Mezzio still registers every route on
 * each boot; that's how its cache works.
 */
final class MezzioContender implements Contender
{
    private const HANDLERS = [
        'index' => Mezzio\IndexHandler::class,
        'show'  => Mezzio\ShowHandler::class,
        'item'  => Mezzio\ItemHandler::class,
        'store' => Mezzio\StoreHandler::class,
    ];

    private Application $app;

    public function name(): string
    {
        return 'mezzio';
    }

    public function boot(RouteSet $routes, ?string $cacheDir): void
    {
        $config = (new ConfigAggregator([
            \Laminas\HttpHandlerRunner\ConfigProvider::class,
            \Mezzio\Router\FastRouteRouter\ConfigProvider::class,
            \Laminas\Diactoros\ConfigProvider::class,
            \Mezzio\ConfigProvider::class,
            \Mezzio\Router\ConfigProvider::class,
            new ArrayProvider([
                ConfigAggregator::ENABLE_CACHE => $cacheDir !== null,
                'dependencies' => ['invokables' => array_combine(self::HANDLERS, self::HANDLERS)],
                'router' => ['fastroute' => [
                    FastRouteRouter::CONFIG_CACHE_ENABLED => $cacheDir !== null,
                    FastRouteRouter::CONFIG_CACHE_FILE => "$cacheDir/mezzio-routes.php",
                ]],
            ]),
        ], $cacheDir === null ? null : "$cacheDir/mezzio-config.php"))->getMergedConfig();

        $dependencies = $config['dependencies'];
        $dependencies['services']['config'] = $config;
        $this->app = (new ServiceManager($dependencies))->get(Application::class);

        $this->app->pipe(ErrorHandler::class);
        $this->app->pipe(RouteMiddleware::class);
        $this->app->pipe(ImplicitHeadMiddleware::class);
        $this->app->pipe(ImplicitOptionsMiddleware::class);
        $this->app->pipe(MethodNotAllowedMiddleware::class);
        $this->app->pipe(DispatchMiddleware::class);
        $this->app->pipe(NotFoundHandler::class);

        foreach ($routes->routes() as [$method, $path, $action]) {
            $this->app->route($path, self::HANDLERS[$action], [$method]);
        }
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
