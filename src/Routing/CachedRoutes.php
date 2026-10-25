<?php

declare(strict_types=1);

namespace Dromos\Routing;

use Closure;

/**
 * What the route cache stores: the compiled table for a set of routes, the
 * signature identifying that set, its controller handlers and their
 * middleware class names (closures and middleware instances can't be stored).
 *
 * The signature is either a hash of the registered routes (signatureOf(),
 * for Router::enableCache()) or of the routes file they came from
 * (signatureOfFile(), for Router::loadRoutes(), which dispatches the stored
 * handlers without registering anything).
 */
final readonly class CachedRoutes
{
    /**
     * @param array<string, array<string, array{0:class-string,1:string}>> $handlers   Controller handlers by method, then route URL
     * @param array<string, list<class-string>>                            $middleware Middleware class names keyed by "METHOD:/path"
     */
    public function __construct(
        public string $signature,
        public CompiledRoutes $table,
        public array $handlers,
        public array $middleware = [],
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
     * A signature that changes whenever the file is edited or replaced (its
     * inode, size or modification time changes), or null if it doesn't exist.
     */
    public static function signatureOfFile(string $file): ?string
    {
        // One stat() call: fileinode() and filesize() reuse what filemtime() read.
        // Runs on every cached request, so it builds no stat() array and no hash
        $mtime = @filemtime($file);
        if ($mtime === false) {
            return null;
        }
        return 'file:' . $file . "\0" . fileinode($file) . "\0" . filesize($file) . "\0" . $mtime;
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
