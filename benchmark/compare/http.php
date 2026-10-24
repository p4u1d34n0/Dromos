<?php

declare(strict_types=1);

/**
 * HTTP comparison: real requests to PHP's built-in server with opcache on,
 * one PHP request per HTTP request as under PHP-FPM. Unlike inprocess.php,
 * this counts loading each router's files, building its request and sending
 * its response. Requests are sent one at a time with ab (ApacheBench).
 *
 * Usage: php benchmark/compare/http.php [--routers=dromos,flight] [--sizes=5,50,500] [--requests=3000]
 */

use Bench\Contenders;
use Bench\RouteSet;

require __DIR__ . '/vendor/autoload.php';

$options = getopt('', ['routers::', 'sizes::', 'requests::']);
$routers = explode(',', $options['routers'] ?? implode(',', array_keys(Contenders::ALL)));
$sizes = array_map('intval', explode(',', $options['sizes'] ?? '5,50,500'));
$requests = (int) ($options['requests'] ?? 3000);

if (trim((string) shell_exec('command -v ab')) === '') {
    fwrite(STDERR, "ab (ApacheBench) is required.\n");
    exit(1);
}

$cacheRoot = sys_get_temp_dir() . '/dromos-compare-http-' . getmypid();
$port = 8710;

/** Start the built-in server for one router and route set; returns the process. */
function start_server(int $port, string $router, int $resources, ?string $cacheDir, string $script = 'index.php')
{
    $env = ['BENCH_ROUTER' => $router, 'BENCH_RESOURCES' => (string) $resources, 'BENCH_CACHE_DIR' => (string) $cacheDir] + getenv();
    $command = [
        PHP_BINARY,
        '-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1',
        '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.file_update_protection=0',
        '-d', 'error_log=/dev/null', '-d', 'display_errors=0',
        '-S', "127.0.0.1:$port", __DIR__ . "/public/$script",
    ];
    $process = proc_open($command, [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes, __DIR__ . '/public', $env);
    for ($i = 0; $i < 100; $i++) {
        if (@fsockopen('127.0.0.1', $port)) {
            return $process;
        }
        usleep(50_000);
    }
    throw new RuntimeException("server for $router did not start");
}

function fetch(int $port, string $method, string $path): array
{
    $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true]]);
    $body = @file_get_contents("http://127.0.0.1:$port$path", false, $context);
    preg_match('/^HTTP\/\S+ (\d+)/', $http_response_header[0] ?? '', $m);
    return [(int) ($m[1] ?? 0), (string) $body];
}

/** Mean µs per request over $n sequential requests, after a warm-up. */
function ab(int $port, string $method, string $path, int $n): float
{
    $post = $method === 'POST' ? '-m POST ' : '';
    shell_exec("ab -q -n 200 -c 1 $post" . escapeshellarg("http://127.0.0.1:$port$path") . ' 2>&1');
    $out = (string) shell_exec("ab -q -n $n -c 1 $post" . escapeshellarg("http://127.0.0.1:$port$path") . ' 2>&1');
    if (!preg_match('/Time per request:\s+([\d.]+) \[ms\] \(mean\)/', $out, $m)) {
        throw new RuntimeException("ab failed:\n$out");
    }
    return (float) $m[1] * 1000;
}

$server = start_server(++$port, '', 0, null, 'baseline.php');
$baseline = ab($port, 'GET', '/', $requests);
proc_terminate($server);
proc_close($server);

$results = [];
foreach ($sizes as $resources) {
    $routes = new RouteSet($resources);
    foreach ($routers as $name) {
        foreach ([false, true] as $cached) {
            $cacheDir = null;
            if ($cached) {
                $cacheDir = "$cacheRoot/$name-$resources";
                @mkdir($cacheDir, 0777, true);
            }
            $server = start_server(++$port, $name, $resources, $cacheDir);
            try {
                foreach ($routes->requests() as $label => [$method, $path, $status, $body]) {
                    [$gotStatus, $gotBody] = fetch($port, $method, $path);
                    if ($gotStatus !== $status || ($body !== null && $gotBody !== $body)) {
                        throw new RuntimeException("$name $label: expected $status $body, got $gotStatus $gotBody");
                    }
                }
                foreach (['static, last', 'param, last', '404'] as $label) {
                    [$method, $path] = $routes->requests()[$label];
                    $results[$routes->count()][($cached ? 'cached: ' : '') . $label][$name] = ab($port, $method, $path, $requests);
                }
            } finally {
                proc_terminate($server);
                proc_close($server);
            }
        }
    }
}
shell_exec('rm -rf ' . escapeshellarg($cacheRoot));

printf("PHP %s built-in server, opcache on, µs per request (lower is better); fastest in each row marked *\n", PHP_VERSION);
printf("A script that only echoes the JSON takes %.0f µs: the server and ab's share of every number below.\n\n", $baseline);
foreach ($results as $count => $rows) {
    printf("%d routes\n%-24s", $count, '');
    foreach ($routers as $name) {
        printf('%12s', $name);
    }
    echo "\n";
    foreach ($rows as $label => $times) {
        printf('%-24s', $label);
        $best = min($times);
        foreach ($routers as $name) {
            printf('%11.0f%s', $times[$name], $times[$name] === $best ? '*' : ' ');
        }
        echo "\n";
    }
    echo "\n";
}
