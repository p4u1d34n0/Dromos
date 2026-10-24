<?php

declare(strict_types=1);

/**
 * Matching only: each router's URL matcher read from its compiled cache,
 * with no request, response, middleware or handler call. FastRoute and
 * Symfony Routing are matchers only, so this is where they and Dromos
 * compare like for like; inprocess.php times whole requests.
 *
 *   worker      matcher loaded once, then one path after another
 *   fpm cached  load the compiled routes and match one path, every time
 *
 * Usage: php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 \
 *            benchmark/compare/matchers.php [--routers=dromos,symfony] [--sizes=5,50,500] [--budget=0.3] [--json=file]
 */

use Bench\Matchers;
use Bench\Results;
use Bench\RouteSet;
use Bench\Timer;

require __DIR__ . '/vendor/autoload.php';

ini_set('memory_limit', '1G');

$options = getopt('', ['routers::', 'sizes::', 'budget::', 'json::']);
$routers = explode(',', $options['routers'] ?? implode(',', array_keys(Matchers::ALL)));
$sizes = array_map('intval', explode(',', $options['sizes'] ?? '5,50,500'));
$budget = (float) ($options['budget'] ?? 0.3);

$cacheDir = sys_get_temp_dir() . '/dromos-matchers-' . getmypid();
@mkdir($cacheDir);

$results = new Results('matchers');
$results->note('opcache', function_exists('opcache_get_status') && @opcache_get_status(false) !== false);
foreach ($sizes as $resources) {
    $routes = new RouteSet($resources);
    foreach ($routers as $name) {
        // A directory per matcher and route set, as in inprocess.php
        $routerCache = "$cacheDir/$name-{$routes->count()}";
        mkdir($routerCache);
        Matchers::create($name)->boot($routes, $routerCache); // writes the cache
        $matcher = Matchers::create($name);
        $matcher->boot($routes, $routerCache); // reads it
        Matchers::verify($matcher, $routes);

        foreach ($routes->requests() as $label => [$method, $path]) {
            $results->add($routes->count(), "worker: $label", $name, ...Timer::rounds(fn() => $matcher->match($method, $path), $budget));
        }
        [$method, $path] = $routes->requests()['param, last'];
        $results->add($routes->count(), 'fpm cached: boot + param, last', $name, ...Timer::rounds(function () use ($name, $routes, $routerCache, $method, $path) {
            $matcher = Matchers::create($name);
            $matcher->boot($routes, $routerCache);
            $matcher->match($method, $path);
        }, $budget));
    }
}
shell_exec('rm -rf ' . escapeshellarg($cacheDir));

if (isset($options['json'])) {
    $results->save($options['json']);
}

printf("PHP %s, opcache %s, µs per match (lower is better); fastest in each row marked *\n\n",
    PHP_VERSION, function_exists('opcache_get_status') && @opcache_get_status(false) ? 'on' : 'off');
echo $results->table($routers, 2);
