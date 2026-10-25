<?php

declare(strict_types=1);

namespace Dromos\Env;

/**
 * Loads KEY=value lines from a .env file into $_ENV and putenv().
 *
 * - Blank lines, comments (#) and lines without a name or an "=" are skipped.
 * - Variables that are already set are never overwritten.
 * - Each file is loaded once per process. Long-running servers (OpenSwoole,
 *   RoadRunner) can call load() on every request without re-reading the
 *   file; a different file still loads.
 */
class EnvLoader
{
    /** @var array<string, true> Files already loaded, by resolved path */
    private static array $loaded = [];

    public static function load(string $path): void
    {
        $key = realpath($path);
        if ($key === false || isset(self::$loaded[$key])) {
            return; // missing, or loaded already
        }
        self::$loaded[$key] = true;

        $lines = @file($key, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$name, $value] = array_map('trim', $parts);
            if ($name === '' || array_key_exists($name, $_ENV)) {
                continue;
            }

            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        $env = getenv($key);
        return $env !== false ? $env : $default;
    }

    /**
     * Forget which files were loaded, so they load again.
     *
     * @internal For tests. Variables already set stay set.
     */
    public static function reset(): void
    {
        self::$loaded = [];
    }
}
