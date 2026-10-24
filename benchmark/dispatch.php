<?php

/**
 * Router dispatch micro-benchmark (in-process, no HTTP).
 *
 * worker: routes registered once, then many requests (OpenSwoole / RoadRunner).
 * fpm:    routes registered and one request dispatched each time (PHP-FPM).
 *
 * Usage: php -d opcache.enable_cli=1 benchmark/dispatch.php [path/to/src]
 */

$src = rtrim($argv[1] ?? __DIR__ . '/../src', '/');
spl_autoload_register(function (string $class) use ($src) {
    if (!str_starts_with($class, 'Dromos\\')) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, 7));
    foreach ([$rel, preg_replace('#^Http/#', 'HTTP/', $rel)] as $candidate) {
        if (is_file("$src/$candidate.php")) {
            require "$src/$candidate.php";
            return;
        }
    }
});
ini_set('error_log', '/dev/null');

use Dromos\Router;
use Dromos\Http\Request;
use Dromos\Http\Response;

// Each "resource" adds 3 GET routes and 1 POST route
function register(int $resources): void
{
    Router::reset();
    for ($i = 0; $i < $resources; $i++) {
        Router::Get("/res$i", fn(Request $q, Response $r) => $r->json(['ok' => true]));
        Router::Get("/res$i/{id}", fn(Request $q, Response $r) => $r->json(['id' => $q->getAttribute('id')]));
        Router::Get("/res$i/{id}/items/{item}", fn(Request $q, Response $r) => $r->json(['item' => $q->getAttribute('item')]));
        Router::Post("/res$i", fn(Request $q, Response $r) => $r->json(['ok' => true]));
    }
}

function dispatch(string $method, string $path): int
{
    $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path];
    return (new Router())->handle(new Request())->getStatusCode();
}

function time_us(callable $fn, float $budget = 0.4): float
{
    $fn();
    $n = 0;
    $start = hrtime(true);
    do {
        $fn();
        $n++;
    } while (hrtime(true) - $start < $budget * 1e9);
    return (hrtime(true) - $start) / $n / 1000;
}

$label = basename(dirname($src)) . '/' . basename($src);
printf("%-22s %-8s %-30s %10s\n", $label, 'mode', 'request', 'µs/req');
foreach ([5, 50, 500] as $resources) {
    $last = $resources - 1;
    $requests = [
        'static, first'         => ['GET', '/res0'],
        'static, last'          => ['GET', "/res$last"],
        'param, last'           => ['GET', "/res$last/42"],
        '2 params, last'        => ['GET', "/res$last/42/items/7"],
        '404'                   => ['GET', '/nope/nope'],
    ];
    $routes = $resources * 4;
    register($resources);
    foreach ($requests as $name => [$method, $path]) {
        dispatch($method, $path) < 500 or exit("bad response for $path\n");
        printf("%-22s %-8s %-30s %10.2f\n", "$routes routes", 'worker', $name, time_us(fn() => dispatch($method, $path)));
    }
    [$method, $path] = $requests['param, last'];
    printf("%-22s %-8s %-30s %10.2f\n", "$routes routes", 'fpm', 'register + param, last', time_us(function () use ($resources, $method, $path) {
        register($resources);
        dispatch($method, $path);
    }));
}
