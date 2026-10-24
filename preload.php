<?php

/**
 * Opcache preload script: compiles every Dromos class into shared memory
 * when PHP-FPM starts, so requests don't load or link Dromos's files.
 *
 * In php.ini:
 *
 *   opcache.preload=/path/to/app/vendor/pauldeano/dromos/preload.php
 *   opcache.preload_user=www-data
 *
 * Restart PHP-FPM after upgrading Dromos, as preloaded code is never
 * reloaded. If your app has its own preload script, require this one from it.
 */

declare(strict_types=1);

(static function (): void {
    $src = __DIR__ . '/src/';
    $autoload = static function (string $class) use ($src): void {
        if (str_starts_with($class, 'Dromos\\')) {
            $file = $src . str_replace('\\', '/', substr($class, strlen('Dromos\\'))) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    };
    spl_autoload_register($autoload);

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() === 'php') {
            // Loading through the autoloader puts parents and interfaces first, so every class links
            $autoload('Dromos\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($src), -4)));
        }
    }

    spl_autoload_unregister($autoload);
})();
