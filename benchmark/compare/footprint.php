<?php

declare(strict_types=1);

/**
 * Footprint of each router for one request: the router's own files loaded,
 * their lines of code, and peak memory. Each router runs in a fresh process.
 *
 * Usage: php benchmark/compare/footprint.php [--routers=...] [--resources=50]
 */

use Bench\Contenders;
use Bench\RouteSet;

require __DIR__ . '/vendor/autoload.php';

$options = getopt('', ['routers::', 'resources::', 'probe::']);

if (isset($options['probe'])) {
    $before = memory_get_usage();
    $preloaded = get_included_files();
    $routes = new RouteSet((int) $options['resources']);
    $contender = Contenders::create($options['probe']);
    $contender->boot($routes, null);
    [$method, $path] = $routes->requests()['param, last'];
    $contender->handle($method, $path);
    $files = array_values(array_filter(
        array_diff(get_included_files(), $preloaded),
        fn(string $file) => !str_starts_with($file, __DIR__ . '/src/')
    ));
    $lines = array_sum(array_map(fn(string $file) => count(file($file)), $files));
    echo json_encode(['files' => count($files), 'lines' => $lines, 'memory' => memory_get_peak_usage() - $before]);
    exit;
}

$routers = explode(',', $options['routers'] ?? implode(',', array_keys(Contenders::ALL)));
$resources = (int) ($options['resources'] ?? 50);

printf("One request with %d routes, each router in a fresh process (without opcache)\n\n", $resources * 4);
printf("%-12s %8s %14s %14s\n", 'router', 'files', 'lines of code', 'peak memory');
foreach ($routers as $name) {
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' -d opcache.enable_cli=0 ' . escapeshellarg(__FILE__)
        . ' --probe=' . escapeshellarg($name) . " --resources=$resources");
    $r = json_decode((string) $out, true) ?? throw new RuntimeException("probe failed for $name: $out");
    printf("%-12s %8d %14s %11.0f KB\n", $name, $r['files'], number_format($r['lines']), $r['memory'] / 1024);
}
