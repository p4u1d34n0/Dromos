<?php

declare(strict_types=1);

// Front controller for http.php: one request per run, as under PHP-FPM.

require __DIR__ . '/../vendor/autoload.php';

$contender = Bench\Contenders::create((string) getenv('BENCH_ROUTER'));
$cacheDir = getenv('BENCH_CACHE_DIR') ?: null;
$contender->boot(new Bench\RouteSet((int) getenv('BENCH_RESOURCES')), $cacheDir);
$contender->serve();
