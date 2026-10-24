<?php

declare(strict_types=1);

/**
 * Collects a dataset: runs inprocess.php, matchers.php, http.php and footprint.php several
 * times, each in a fresh process and one after another (never in parallel,
 * which skews every number), and saves every run's raw JSON in one
 * directory. report.php turns that directory into tables.
 *
 * Usage: php benchmark/compare/run.php [--runs=5] [--http-runs=3] [--routers=...] [--sizes=5,50,500]
 *                                      [--budget=0.5] [--requests=3000] [--out=results/<name>]
 *
 * Close other programs first, and on a laptop, plug it in.
 */

require __DIR__ . '/vendor/autoload.php';

$options = getopt('', ['runs::', 'http-runs::', 'routers::', 'sizes::', 'budget::', 'requests::', 'out::']);
$runs = (int) ($options['runs'] ?? 5);
$httpRuns = (int) ($options['http-runs'] ?? 3);
$env = Bench\Results::environment();
$out = $options['out'] ?? __DIR__ . '/results/' . gmdate('Y-m-d') . '-' . strtolower(str_replace(' ', '-', PHP_OS_FAMILY . '-' . php_uname('m'))) . '-php' . PHP_VERSION;

if (is_dir($out) && glob("$out/*.json") !== []) {
    fwrite(STDERR, "$out already has results; pass --out to choose another directory.\n");
    exit(1);
}
@mkdir($out, 0777, true);

$shared = array_filter([
    'routers' => $options['routers'] ?? null,
    'sizes'   => $options['sizes'] ?? null,
]);

/** Run one script to completion, passing --name=value options; stop everything if it fails. */
function run_script(string $script, array $phpFlags, array $options): void
{
    $command = escapeshellarg(PHP_BINARY);
    foreach ($phpFlags as $flag) {
        $command .= ' -d ' . escapeshellarg($flag);
    }
    $command .= ' ' . escapeshellarg(__DIR__ . "/$script");
    foreach ($options as $name => $value) {
        $command .= ' ' . escapeshellarg("--$name=$value");
    }
    passthru($command, $status);
    if ($status !== 0) {
        fwrite(STDERR, "$script failed with status $status\n");
        exit($status);
    }
}

printf("Writing to %s\nPHP %s, %s, %s\n\n", $out, $env['php'], $env['os'], $env['cpu'] ?? 'unknown CPU');

for ($i = 1; $i <= $runs; $i++) {
    echo "== in-process, run $i of $runs\n";
    run_script('inprocess.php', ['opcache.enable_cli=1', 'opcache.file_update_protection=0'],
        $shared + ['budget' => $options['budget'] ?? '0.5', 'json' => "$out/inprocess-$i.json"]);
}
// Only some routers have a matcher of their own to time (see matchers.php)
$matchers = array_intersect(explode(',', $options['routers'] ?? implode(',', array_keys(Bench\Matchers::ALL))), array_keys(Bench\Matchers::ALL));
for ($i = 1; $i <= $runs && $matchers !== []; $i++) {
    echo "== matching only, run $i of $runs\n";
    run_script('matchers.php', ['opcache.enable_cli=1', 'opcache.file_update_protection=0'],
        ['routers' => implode(',', $matchers)] + $shared + ['budget' => $options['budget'] ?? '0.5', 'json' => "$out/matchers-$i.json"]);
}
for ($i = 1; $i <= $httpRuns; $i++) {
    echo "== HTTP, run $i of $httpRuns\n";
    run_script('http.php', [], $shared + ['requests' => $options['requests'] ?? '3000', 'json' => "$out/http-$i.json"]);
}
echo "== footprint\n";
run_script('footprint.php', [], array_filter(['routers' => $options['routers'] ?? null]) + ['json' => "$out/footprint.json"]);

echo "\nDone. Tables: php " . basename(__DIR__) . "/report.php " . $out . "\n";
