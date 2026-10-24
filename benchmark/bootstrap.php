<?php

spl_autoload_register(function (string $class) {
    // Dromos namespace
    if (str_starts_with($class, 'Dromos\\')) {
        $relative = str_replace('\\', '/', substr($class, 7));
        $base = '/app/src/';

        $file = $base . $relative . '.php';
        if (file_exists($file)) { require_once $file; return; }
    }

    // Benchmark namespace
    if (str_starts_with($class, 'Benchmark\\')) {
        $relative = str_replace('\\', '/', substr($class, 10));
        $file = '/app/benchmark/src/' . $relative . '.php';
        if (file_exists($file)) { require_once $file; return; }
    }
});
