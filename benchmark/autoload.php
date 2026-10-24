<?php

/**
 * Autoloader for the in-process benchmarks. Loads Dromos from the src
 * directory given (default: this checkout), so two versions can be compared:
 *
 *   php benchmark/dispatch.php ../other-checkout/src
 *
 * Also maps the Dromos\Http namespace onto the src/HTTP/ directory.
 */
return static function (string $src): void {
    $src = rtrim($src, '/');
    spl_autoload_register(static function (string $class) use ($src): void {
        if (!str_starts_with($class, 'Dromos\\')) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen('Dromos\\')));
        foreach ([$relative, preg_replace('#^Http/#', 'HTTP/', $relative)] as $candidate) {
            if (is_file("$src/$candidate.php")) {
                require "$src/$candidate.php";
                return;
            }
        }
    });
    ini_set('error_log', '/dev/null'); // RouterExceptionHandler logs every 404
};
