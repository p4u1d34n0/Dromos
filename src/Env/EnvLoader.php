<?php

declare(strict_types=1);

namespace Dromos\Env;

final class EnvLoader
{
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                continue;
            }

            if (str_starts_with($trimmedLine, '#')) {
                continue;
            }

            $parts = explode('=', $trimmedLine, 2);
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

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $env = getenv($key);

        return $env !== false ? $env : $default;
    }
}
