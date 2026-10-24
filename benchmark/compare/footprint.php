<?php

declare(strict_types=1);

/**
 * Footprint of each router for one request: the router's own files loaded,
 * their lines of code, and peak memory. Each router runs in a fresh process.
 *
 * Usage: php benchmark/compare/footprint.php [--routers=...] [--resources=50] [--json=file]
 */

use Bench\Contenders;
use Bench\RouteSet;

require __DIR__ . '/vendor/autoload.php';

$options = getopt('', ['routers::', 'resources::', 'probe::', 'json::']);

if (isset($options['probe'])) {
    // Measured from here: the autoloader's classmap, loaded above, is the
    // same for every router and can be bigger than any one router's peak
    $before = memory_get_usage();
    memory_reset_peak_usage();
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
$footprint = [];
foreach ($routers as $name) {
    $out = shell_exec(escapeshellarg(PHP_BINARY) . ' -d opcache.enable_cli=0 ' . escapeshellarg(__FILE__)
        . ' --probe=' . escapeshellarg($name) . " --resources=$resources");
    $r = json_decode((string) $out, true) ?? throw new RuntimeException("probe failed for $name: $out");
    printf("%-12s %8d %14s %11.0f KB\n", $name, $r['files'], number_format($r['lines']), $r['memory'] / 1024);
    $footprint[$name] = $r;
}

if (isset($options['json'])) {
    file_put_contents($options['json'], json_encode([
        'script'      => 'footprint',
        'recorded_at' => gmdate('c'),
        'environment' => Bench\Results::environment(),
        'notes'       => ['opcache' => false],
        'routes'      => $resources * 4,
        'footprint'   => $footprint,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
}
