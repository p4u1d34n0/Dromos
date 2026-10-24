<?php

declare(strict_types=1);

namespace Bench;

/**
 * One router under test, wired the way its documentation recommends.
 *
 * boot() starts from nothing and registers the route set, as every PHP-FPM
 * request does. handle() runs one request through routing, the handler and
 * the response object, and returns the status and body without sending them.
 * serve() does the same for a real HTTP request and sends the response with
 * the router's own emitter.
 */
interface Contender
{
    /** Short name used on the command line and in results. */
    public function name(): string;

    /**
     * Register the routes. With a cache directory, use the router's own
     * route cache (compiling once, then reading the cached form).
     */
    public function boot(RouteSet $routes, ?string $cacheDir): void;

    /** @return array{0:int,1:string} Status code and body */
    public function handle(string $method, string $path): array;

    /** Handle the current HTTP request ($_SERVER) and send the response. */
    public function serve(): void;
}
