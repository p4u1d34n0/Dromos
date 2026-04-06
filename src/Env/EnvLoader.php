<?php

namespace Dromos\Env;

class EnvLoader
{
    private static bool $loaded = false;

    /**
     * Load environment variables from a .env file into $_ENV and putenv().
     *
     * Must be called once at bootstrap — before $server->start() in Swoole/ReactPHP
     * contexts. Subsequent calls are safely ignored due to the static guard.
     *
     * Note: putenv() sets process-level environment which is shared across
     * coroutines in Swoole workers. Read env values into application config
     * at bootstrap rather than using getenv() per-request.
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$name, $value] = array_map('trim', $parts);

            if ($name === '') {
                continue;
            }

            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
                putenv("$name=$value");
            }
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
     * Reset the loaded guard.
     *
     * @internal For testing only — do not call in production code.
     */
    public static function reset(): void
    {
        self::$loaded = false;
    }
}
