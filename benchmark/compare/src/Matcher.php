<?php

declare(strict_types=1);

namespace Bench;

/**
 * One router's URL matcher on its own, read from its compiled cache: no
 * request or response objects, no middleware and no handler call. This is
 * the work FastRoute and Symfony Routing do, so all three compare like for
 * like here, where Contender compares whole requests.
 */
interface Matcher
{
    /** Short name used on the command line and in results; the same as the router's Contender. */
    public function name(): string;

    /**
     * Load the compiled routes from the cache directory, compiling and
     * writing them first if the directory has none, as a production app does.
     */
    public function boot(RouteSet $routes, string $cacheDir): void;

    /**
     * @return array{0:int,1:?string,2:array<string,string>} Status (200, 404 or 405), the matched
     *         action and its route parameters (null and [] without a match)
     */
    public function match(string $method, string $path): array;
}
