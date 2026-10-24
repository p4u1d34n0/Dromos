<?php

declare(strict_types=1);

/**
 * Turns a dataset from run.php into Markdown tables, and writes every sample
 * to dataset.csv in the same directory.
 *
 * Each cell is the median across runs of each run's own median, with the
 * run-to-run spread: ±5% means the slowest and fastest runs were 10% apart.
 * A wide spread means the machine was busy; collect the dataset again.
 *
 * Usage: php benchmark/compare/report.php results/<name>
 */

require __DIR__ . '/vendor/autoload.php';

use Bench\Contenders;
use Bench\Results;

$dir = rtrim($argv[1] ?? '', '/');
if ($dir === '' || !is_dir($dir)) {
    fwrite(STDERR, "Usage: php report.php <results directory>\n");
    exit(1);
}

/** @return list<array<string, mixed>> Decoded JSON files matching the pattern */
function load(string $pattern): array
{
    $files = glob($pattern) ?: [];
    natsort($files);
    return array_map(fn(string $f) => json_decode((string) file_get_contents($f), true, flags: JSON_THROW_ON_ERROR), array_values($files));
}

$scripts = ['inprocess' => load("$dir/inprocess-*.json"), 'http' => load("$dir/http-*.json")];
$footprint = load("$dir/footprint.json")[0] ?? null;
$all = [...$scripts['inprocess'], ...$scripts['http'], ...array_filter([$footprint])];
if ($all === []) {
    fwrite(STDERR, "No results in $dir\n");
    exit(1);
}

// Every run must come from the same setup, or the medians mix different things
$environment = $all[0]['environment'];
foreach ($all as $run) {
    $differs = array_keys(array_diff_assoc(array_map('json_encode', $run['environment']), array_map('json_encode', $environment)));
    if ($differs !== []) {
        fwrite(STDERR, 'Warning: runs differ in ' . implode(', ', $differs) . "\n");
    }
}

$csv = fopen("$dir/dataset.csv", 'w');
fputcsv($csv, ['script', 'run', 'routes', 'row', 'router', 'sample', 'us_per_request'], escape: '');

printf("PHP %s, %s, %s; JIT %s, Xdebug %s; Dromos %s\n\n",
    $environment['php'], $environment['os'], $environment['cpu'] ?? 'unknown CPU',
    $environment['jit'], $environment['xdebug'] ? 'on' : 'off', $environment['dromos'] ?? 'unknown');

/** "fpm cached: boot + param, last" => "PHP-FPM cached: boot + param, last" */
function label(int|string $row): string
{
    $row = (string) $row;
    return str_starts_with($row, 'fpm') ? 'PHP-FPM' . substr($row, 3) : ucfirst($row);
}

foreach ($scripts as $script => $runs) {
    if ($runs === []) {
        continue;
    }
    // routes => row => router => one median per run
    $perRun = [];
    foreach ($runs as $i => $run) {
        foreach ($run['samples'] as $routes => $rows) {
            foreach ($rows as $row => $routers) {
                foreach ($routers as $router => $samples) {
                    $perRun[$routes][$row][$router][] = Results::median($samples);
                    foreach ($samples as $n => $sample) {
                        fputcsv($csv, [$script, $i + 1, $routes, $row, $router, $n + 1, $sample], escape: '');
                    }
                }
            }
        }
    }

    $title = $script === 'inprocess' ? 'In-process' : 'HTTP';
    $opcache = array_unique(array_map(fn(array $run) => $run['notes']['opcache'] ? 'on' : 'off', $runs));
    printf("## %s: µs per request, median of %d run%s, opcache %s\n\n", $title, count($runs), count($runs) === 1 ? '' : 's', implode('/', $opcache));
    if ($script === 'http') {
        $floors = array_map(fn(array $run) => $run['notes']['floor_us'], $runs);
        printf("PHP's built-in server and ab alone take %.0f µs, and that is part of every number below.\n\n", Results::median($floors));
    }
    $decimals = $script === 'inprocess' ? 2 : 0;

    foreach ($perRun as $routes => $rows) {
        $routers = array_values(array_intersect(array_keys(Contenders::ALL), array_keys(reset($rows))));
        uksort($rows, Results::compareRows(...));
        printf("| %s routes | %s |\n|---|%s\n", number_format($routes), implode(' | ', $routers), str_repeat('---|', count($routers)));
        foreach ($rows as $row => $byRouter) {
            $medians = array_map(Results::median(...), $byRouter);
            $best = min($medians);
            $cells = [];
            foreach ($routers as $router) {
                $median = $medians[$router];
                $spread = count($byRouter[$router]) > 1 ? (max($byRouter[$router]) - min($byRouter[$router])) / 2 / $median * 100 : 0;
                $cell = number_format($median, $decimals) . sprintf(' ±%.0f%%', $spread);
                $cells[] = $median === $best ? "**$cell**" : $cell;
            }
            printf("| %s | %s |\n", label($row), implode(' | ', $cells));
        }
        echo "\n";
    }
}
fclose($csv);

if ($footprint !== null) {
    printf("## Footprint: one request with %d routes, in a fresh process without opcache\n\n", $footprint['routes']);
    echo "| | Files | Lines of code | Peak memory |\n|---|---|---|---|\n";
    foreach ($footprint['footprint'] as $router => $r) {
        printf("| %s | %d | %s | %s KB |\n", $router, $r['files'], number_format($r['lines']), number_format($r['memory'] / 1024));
    }
    echo "\n";
}

echo "Package versions: " . implode(', ', array_map(fn($p, $v) => "$p $v", array_keys($environment['packages']), $environment['packages'])) . "\n";
echo "Every sample: $dir/dataset.csv\n";
