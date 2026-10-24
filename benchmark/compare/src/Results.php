<?php

declare(strict_types=1);

namespace Bench;

use Composer\InstalledVersions;

/**
 * Raw measurements from one run of a benchmark script, with the environment
 * they were taken in, written as JSON so runs can be compared and the
 * published tables rebuilt from data (see report.php).
 *
 * Each sample is one timing in µs per request; a row keeps every sample, so
 * the spread is visible, not only the median.
 */
final class Results
{
    /** @var array<int, array<string, array<string, list<float>>>> routes => row => router => samples */
    private array $samples = [];

    /** @var array<string, mixed> */
    private array $extra = [];

    public function __construct(private readonly string $script)
    {
    }

    public function add(int $routes, string $row, string $router, float ...$samples): void
    {
        $this->samples[$routes][$row][$router] = [...$this->samples[$routes][$row][$router] ?? [], ...$samples];
    }

    /** Anything else worth keeping with the run, e.g. the HTTP floor. */
    public function note(string $key, mixed $value): void
    {
        $this->extra[$key] = $value;
    }

    /** @return array<int, array<string, array<string, float>>> routes => row => router => median */
    public function medians(): array
    {
        return array_map(
            fn(array $rows) => array_map(fn(array $routers) => array_map(self::median(...), $routers), $rows),
            $this->samples
        );
    }

    /**
     * The medians as plain-text tables, one per route count, fastest in each
     * row marked *.
     *
     * @param list<string> $routers Column order
     */
    public function table(array $routers, int $decimals): string
    {
        $out = '';
        foreach ($this->medians() as $count => $rows) {
            $out .= sprintf("%d routes\n%-32s", $count, '');
            foreach ($routers as $name) {
                $out .= sprintf('%12s', $name);
            }
            $out .= "\n";
            uksort($rows, self::compareRows(...));
            foreach ($rows as $label => $times) {
                $out .= sprintf('%-32s', $label);
                $best = min($times);
                foreach ($routers as $name) {
                    $out .= sprintf("%11.{$decimals}f%s", $times[$name], $times[$name] === $best ? '*' : ' ');
                }
                $out .= "\n";
            }
            $out .= "\n";
        }
        return $out;
    }

    public function save(string $file): void
    {
        $data = [
            'script'      => $this->script,
            'recorded_at' => gmdate('c'),
            'environment' => self::environment(),
            'notes'       => $this->extra,
            'samples'     => $this->samples,
        ];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    /** Worker rows, then uncached, then cached; otherwise the order they were recorded in. */
    public static function compareRows(int|string $a, int|string $b): int
    {
        // PHP turns a row named "404" into an integer key
        $rank = fn(int|string $row) => [!str_starts_with((string) $row, 'worker'), str_contains((string) $row, 'cached')];
        return $rank($a) <=> $rank($b);
    }

    /** @param list<float> $values */
    public static function median(array $values): float
    {
        sort($values);
        $mid = intdiv(count($values), 2);
        return count($values) % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }

    /**
     * What the numbers depend on. The host name is left out on purpose:
     * results are meant to be committed.
     *
     * @return array<string, mixed>
     */
    public static function environment(): array
    {
        return [
            'php'      => PHP_VERSION,
            'os'       => PHP_OS_FAMILY . ' ' . php_uname('r') . ' ' . php_uname('m'),
            'cpu'      => self::cpu(),
            // Whether opcache is on depends on the script (see each run's notes); the JIT setting doesn't
            'jit'      => (string) (ini_get('opcache.jit') ?: 'disable'),
            'xdebug'   => extension_loaded('xdebug'),
            'dromos'   => trim((string) shell_exec('git -C ' . escapeshellarg(__DIR__) . ' describe --always --dirty 2>/dev/null')) ?: null,
            'packages' => self::packages(),
        ];
    }

    private static function cpu(): ?string
    {
        if (PHP_OS_FAMILY === 'Darwin') {
            $name = trim((string) shell_exec('sysctl -n machdep.cpu.brand_string 2>/dev/null'));
            $cores = trim((string) shell_exec('sysctl -n hw.ncpu 2>/dev/null'));
            return $name === '' ? null : "$name, $cores cores";
        }
        $info = @file_get_contents('/proc/cpuinfo');
        if ($info === false || !preg_match('/^model name\s*:\s*(.+)$/m', $info, $m)) {
            return null;
        }
        return trim($m[1]) . ', ' . preg_match_all('/^processor\s*:/m', $info) . ' cores';
    }

    /** @return array<string, string> The routers' package versions */
    private static function packages(): array
    {
        $versions = [];
        foreach (['flightphp/core', 'illuminate/routing', 'nikic/fast-route', 'symfony/routing', 'slim/slim', 'mezzio/mezzio', 'mezzio/mezzio-fastroute'] as $package) {
            if (InstalledVersions::isInstalled($package)) {
                $versions[$package] = (string) InstalledVersions::getPrettyVersion($package);
            }
        }
        return $versions;
    }
}
