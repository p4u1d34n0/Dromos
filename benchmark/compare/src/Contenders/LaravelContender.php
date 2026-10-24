<?php

declare(strict_types=1);

namespace Bench\Contenders;

use Bench\Contender;
use Bench\RouteSet;
use Bench\Controllers\LaravelController;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * illuminate/routing on its own, as a Laravel app uses it. With a cache
 * directory it works like `php artisan route:cache`: routes are registered
 * and compiled once, and later boots load the compiled routes without
 * registering anything. A full Laravel app adds its HTTP kernel, service
 * providers and exception handler on top; those are left out, so these
 * numbers flatter Laravel. A 404 is turned into a JSON response here, as an
 * app's exception handler would.
 */
final class LaravelContender implements Contender
{
    private Router $router;

    public function name(): string
    {
        return 'laravel';
    }

    public function boot(RouteSet $routes, ?string $cacheDir): void
    {
        $container = new Container();
        $this->router = new Router(new Dispatcher($container), $container);

        $cacheFile = $cacheDir === null ? null : "$cacheDir/laravel-routes.php";
        if ($cacheFile !== null && is_file($cacheFile)) {
            $this->router->setCompiledRoutes(require $cacheFile);
            return;
        }

        foreach ($routes->routes() as [$method, $path, $action]) {
            $this->router->addRoute($method, $path, [LaravelController::class, $action]);
        }
        if ($cacheFile !== null) {
            $compiled = $this->router->getRoutes()->compile();
            file_put_contents($cacheFile, '<?php return ' . var_export($compiled, true) . ';');
            $this->router->setCompiledRoutes($compiled);
        }
    }

    public function handle(string $method, string $path): array
    {
        $response = $this->dispatch(Request::create($path, $method));
        return [$response->getStatusCode(), (string) $response->getContent()];
    }

    public function serve(): void
    {
        $this->dispatch(Request::capture())->send();
    }

    private function dispatch(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpExceptionInterface $e) {
            return new JsonResponse(['message' => 'Not Found'], $e->getStatusCode());
        }
    }
}
