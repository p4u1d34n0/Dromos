<?php

declare(strict_types=1);

namespace Dromos\Routing;

use Closure;

/**
 * What the route cache stores: the compiled table for a set of routes, the
 * signature identifying that set, and its controller handlers.
 *
 * The handlers only serve the deprecated mode where routes are dispatched
 * from the cache without being registered (closures can't be stored).
 */
final readonly class CachedRoutes
{
    /**
     * @param array<string, array<string, array{0:class-string,1:string}>> $handlers Controller handlers by method, then route URL
     */
    public function __construct(
        public string $signature,
        public CompiledRoutes $table,
        public array $handlers,
    ) {
    }

    /**
     * The controller handlers of a set of routes (closures can't be stored).
     *
     * @param array<string, array<string, Closure|array{0:class-string,1:string}>> $routes Handlers by method, then route URL
     * @return array<string, array<string, array{0:class-string,1:string}>>
     */
    public static function handlersOf(array $routes): array
    {
        $handlers = [];
        foreach ($routes as $method => $list) {
            foreach ($list as $url => $handler) {
                if (is_array($handler)) {
                    $handlers[$method][(string) $url] = $handler;
                }
            }
        }
        return $handlers;
    }

    /**
     * A hash that changes whenever a route is added, removed, reordered or
     * pointed at a different controller method.
     *
     * @param array<string, array<string, Closure|array{0:class-string,1:string}>> $routes
     */
    public static function signatureOf(array $routes): string
    {
        $lines = [];
        foreach ($routes as $method => $list) {
            $lines[] = $method;
            foreach ($list as $url => $handler) {
                $lines[] = is_array($handler) ? $url . ' ' . $handler[0] . '::' . $handler[1] : (string) $url;
            }
        }
        return hash('xxh128', implode("\n", $lines));
    }
}
