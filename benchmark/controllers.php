<?php

/**
 * PHP-FPM-style request with controller routes: register 200 routes across
 * 50 controller classes, then dispatch one request. Shows what controller
 * constructors cost per request.
 *
 * Usage: php -d opcache.enable_cli=1 benchmark/controllers.php [empty|pdo] [path/to/src]
 *   empty  controllers with empty constructors
 *   pdo    controllers that open a (SQLite in-memory) database connection
 */

$kind = $argv[1] ?? 'empty';
$src = rtrim($argv[2] ?? __DIR__ . '/../src', '/');
spl_autoload_register(function (string $class) use ($src) {
    if (!str_starts_with($class, 'Dromos\\')) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, 7));
    foreach ([$rel, preg_replace('#^Http/#', 'HTTP/', $rel)] as $candidate) {
        if (is_file("$src/$candidate.php")) {
            require "$src/$candidate.php";
            return;
        }
    }
});
ini_set('error_log', '/dev/null');

use Dromos\Router;
use Dromos\Http\Request;

// Generate 50 distinct controller classes
$constructor = $kind === 'pdo' ? '$this->db = new \PDO("sqlite::memory:");' : '';
for ($c = 0; $c < 50; $c++) {
    eval("final class BenchController$c {
        private \$db;
        public function __construct() { $constructor }
        public function index(\\Dromos\\Http\\Response \$r) { return \$r->json([]); }
        public function show(\\Dromos\\Http\\Response \$r, string \$id) { return \$r->json(['id' => \$id]); }
        public function store(\\Dromos\\Http\\Response \$r) { return \$r->json([]); }
        public function update(\\Dromos\\Http\\Response \$r, string \$id) { return \$r->json([]); }
    }");
}

$request = function (): void {
    Router::reset();
    for ($c = 0; $c < 50; $c++) {
        Router::Get("/c$c", ["BenchController$c", 'index']);
        Router::Get("/c$c/{id}", ["BenchController$c", 'show']);
        Router::Post("/c$c", ["BenchController$c", 'store']);
        Router::Put("/c$c/{id}", ["BenchController$c", 'update']);
    }
    (new Router())->handle(Request::create('GET', '/c40/7'));
};

$request();
$best = INF;
for ($run = 0; $run < 3; $run++) {
    $n = 0;
    $start = hrtime(true);
    do {
        $request();
        $n++;
    } while (hrtime(true) - $start < 0.4e9);
    $best = min($best, (hrtime(true) - $start) / $n / 1000);
}
printf("%s constructors: %.1f µs per request (best of 3)\n", $kind, $best);
