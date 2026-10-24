<?php

declare(strict_types=1);

/**
 * In-process comparison: the routing work per request, without HTTP.
 *
 *   worker      routes registered once (from the route cache where the router
 *               has one), then one request after another (OpenSwoole,
 *               RoadRunner, FrankenPHP worker mode)
 *   fpm         register the routes and handle one request, every time
 *   fpm cached  the same with each router's own route cache
 *
 * Classes stay loaded between iterations, so the cost of loading each
 * router's files is not counted here; http.php measures that.
 *
 * Usage: php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 \
 *            benchmark/compare/inprocess.php [--routers=dromos,flight] [--sizes=5,50,500] [--budget=0.3]
 */

use Bench\Contenders;
use Bench\RouteSet;

require __DIR__ . '/vendor/autoload.php';

ini_set('error_log', '/dev/null'); // Dromos logs every 404
ini_set('memory_limit', '1G');

$options = getopt('', ['routers::', 'sizes::', 'budget::']);
$routers = explode(',', $options['routers'] ?? implode(',', array_keys(Contenders::ALL)));
$sizes = array_map('intval', explode(',', $options['sizes'] ?? '5,50,500'));
$budget = (float) ($options['budget'] ?? 0.3);

/** Median µs per call over 5 rounds of $budget seconds each. */
function time_us(callable $fn, float $budget): float
{
    $rounds = [];
    for ($round = 0; $round < 5; $round++) {
        $fn();
        $n = 0;
        $start = hrtime(true);
        do {
            $fn();
            $n++;
        } while (hrtime(true) - $start < $budget / 5 * 1e9);
        $rounds[] = (hrtime(true) - $start) / $n / 1000;
    }
    sort($rounds);
    return $rounds[2];
}

$cacheDir = sys_get_temp_dir() . '/dromos-compare-' . getmypid();
@mkdir($cacheDir);

$results = [];
foreach ($sizes as $resources) {
    $routes = new RouteSet($resources);
    $requests = $routes->requests();

    foreach ($routers as $name) {
        [$method, $path] = $requests['param, last'];
        $results[$routes->count()]['fpm: boot + param, last'][$name] = time_us(function () use ($name, $routes, $method, $path) {
            $contender = Contenders::create($name);
            $contender->boot($routes, null);
            $contender->handle($method, $path);
        }, $budget);

        // A directory per router and route set: opcache would otherwise keep
        // serving a cache file compiled for the previous route set
        $routerCache = "$cacheDir/$name-{$routes->count()}";
        mkdir($routerCache);
        $cached = Contenders::create($name);
        $cached->boot($routes, $routerCache); // writes the cache
        $cached = Contenders::create($name);
        $cached->boot($routes, $routerCache); // reads it
        Contenders::verify($cached, $routes);
        // Workers run the router's fastest configuration: its route cache where it has one
        foreach ($requests as $label => [$m, $p]) {
            $results[$routes->count()]["worker: $label"][$name] = time_us(fn() => $cached->handle($m, $p), $budget);
        }
        $results[$routes->count()]['fpm cached: boot + param, last'][$name] = time_us(function () use ($name, $routes, $method, $path, $routerCache) {
            $contender = Contenders::create($name);
            $contender->boot($routes, $routerCache);
            $contender->handle($method, $path);
        }, $budget);
    }
}
shell_exec('rm -rf ' . escapeshellarg($cacheDir));

printf("PHP %s, opcache %s, µs per request (lower is better); fastest in each row marked *\n\n",
    PHP_VERSION, function_exists('opcache_get_status') && @opcache_get_status(false) ? 'on' : 'off');
foreach ($results as $count => $rows) {
    printf("%d routes\n%-32s", $count, '');
    foreach ($routers as $name) {
        printf('%12s', $name);
    }
    echo "\n";
    // Worker rows first, then the two FPM rows
    uksort($rows, fn(string $a, string $b) => [!str_starts_with($a, 'worker'), str_contains($a, 'cached')]
        <=> [!str_starts_with($b, 'worker'), str_contains($b, 'cached')]);
    foreach ($rows as $label => $times) {
        printf('%-32s', $label);
        $best = min($times);
        foreach ($routers as $name) {
            printf('%11.2f%s', $times[$name], $times[$name] === $best ? '*' : ' ');
        }
        echo "\n";
    }
    echo "\n";
}
