<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Classes in the Dromos\Http namespace live in src/HTTP/. Composer's PSR-4
// lookup misses them on case-sensitive filesystems (Linux CI), so fall back
// to the upper-case directory. Remove once src/HTTP/ is renamed to src/Http/.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Dromos\\Http\\')) {
        return;
    }
    $file = __DIR__ . '/../src/HTTP/' . str_replace('\\', '/', substr($class, strlen('Dromos\\Http\\'))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
