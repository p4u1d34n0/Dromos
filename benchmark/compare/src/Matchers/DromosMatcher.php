<?php

declare(strict_types=1);

namespace Bench\Matchers;

use Bench\Controllers\DromosController;
use Bench\Matcher;
use Bench\RouteSet;
use Dromos\Routing\CachedRoutes;
use Dromos\Routing\CompiledRoutes;
use Dromos\Routing\RouteCacheFile;
use Dromos\Routing\RouteCompiler;

/**
 * The table Router dispatches from, read from the route cache file that
 * Router::loadRoutes() writes, and the handler lookup Router does after a match.
 */
final class DromosMatcher implements Matcher
{
    private CompiledRoutes $table;

    /** @var array<string, array<string, array{0:class-string,1:string}>> */
    private array $handlers;

    public function name(): string
    {
        return 'dromos';
    }

    public function boot(RouteSet $routes, string $cacheDir): void
    {
        $cache = new RouteCacheFile("$cacheDir/dromos-matcher.php");
        $cached = $cache->load();
        if ($cached === null) {
            $handlers = [];
            foreach ($routes->routes() as [$method, $path, $action]) {
                $handlers[$method][$path] = [DromosController::class, $action];
            }
            $cached = new CachedRoutes('bench', (new RouteCompiler())->compile($handlers), $handlers);
            $cache->save($cached);
        }
        $this->table = $cached->table;
        $this->handlers = $cached->handlers;
    }

    public function match(string $method, string $path): array
    {
        $match = $this->table->match($method, $path);
        if ($match === null) {
            return [$this->table->allowedMethods($path, $method) === [] ? 404 : 405, null, []];
        }
        return [200, $this->handlers[$method][$match->url][1], $match->params];
    }
}
