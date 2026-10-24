<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Response;
use Dromos\Router;
use Dromos\Tests\Support\RouterTestCase;
use Dromos\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Router::class)]
final class RouterLoadRoutesTest extends RouterTestCase
{
    use TemporaryDirectory;

    /** How many times a routes file has run. */
    public static int $loads = 0;

    private string $routesFile;
    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        self::$loads = 0;
        $this->routesFile = $this->temporaryDirectory() . '/routes.php';
        $this->cacheFile = $this->temporaryDirectory() . '/routes.cache.php';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeTemporaryDirectory();
    }

    /**
     * Write a routes file whose body is $routes, counting each time it runs.
     * The modification time moves forward on every write, as a deploy would.
     */
    private function writeRoutes(string $routes): void
    {
        static $time = 1_700_000_000;
        $controller = '\\' . LoadRoutesTestController::class;
        file_put_contents($this->routesFile, "<?php\n\nuse Dromos\\Router;\n\n\\" . self::class . "::\$loads++;\n"
            . str_replace('CONTROLLER', $controller, $routes) . "\n");
        touch($this->routesFile, $time += 10);
        clearstatcache();
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->routesFile, true);
        }
    }

    /** A fresh request: router state reset, then the routes loaded. */
    private function load(bool $cached = true): void
    {
        Router::reset();
        Router::loadRoutes($this->routesFile, $cached ? $this->cacheFile : null);
    }

    #[Test]
    public function test_it_registers_the_routes_in_the_file(): void
    {
        $this->writeRoutes("Router::Get('/users/{id}', [CONTROLLER::class, 'show']);");

        $this->load(cached: false);

        $this->assertSame(['id' => '7'], $this->body('GET', '/users/7'));
        $this->assertFileDoesNotExist($this->cacheFile);
    }

    #[Test]
    public function test_cached_routes_are_dispatched_without_running_the_routes_file(): void
    {
        $this->writeRoutes("Router::Get('/users/{id}', [CONTROLLER::class, 'show']);\nRouter::Post('/users', [CONTROLLER::class, 'store']);");

        $this->load();
        $this->assertFileExists($this->cacheFile);
        $this->load();
        $this->load();

        $this->assertSame(1, self::$loads, 'only the first request runs the routes file');
        $this->assertSame(['id' => '7'], $this->body('GET', '/users/7'));
        $this->assertSame(['stored' => true], $this->body('POST', '/users'));
        $this->assertSame(405, $this->statusOf('DELETE', '/users'));
        $this->assertSame(404, $this->statusOf('GET', '/nope'));
    }

    #[Test]
    public function test_changing_the_routes_file_rebuilds_the_cache(): void
    {
        $this->writeRoutes("Router::Get('/old', [CONTROLLER::class, 'store']);");
        $this->load();

        $this->writeRoutes("Router::Get('/new', [CONTROLLER::class, 'store']);");
        $this->load();
        $this->load();

        $this->assertSame(2, self::$loads);
        $this->assertSame(404, $this->statusOf('GET', '/old'));
        $this->assertSame(200, $this->statusOf('GET', '/new'));
    }

    #[Test]
    public function test_closure_routes_run_the_routes_file_every_time(): void
    {
        $this->writeRoutes("Router::Get('/hi', fn(\\Dromos\\Http\\Response \$r) => \$r->json(['hi' => true]));");

        $this->load();
        $this->load();

        $this->assertSame(2, self::$loads, 'closures cannot be cached');
        $this->assertFileDoesNotExist($this->cacheFile);
        $this->assertSame(['hi' => true], $this->body('GET', '/hi'));
    }

    #[Test]
    public function test_routes_with_middleware_instances_run_the_routes_file_every_time(): void
    {
        $this->writeRoutes(<<<'PHP'
            Router::group('/admin', function ($group) {
                $group->middleware(new \Dromos\Tests\LoadRoutesTestMiddleware());
                $group->get('/users/{id}', [CONTROLLER::class, 'show']);
            });
            PHP);

        $this->load();
        $this->load();

        $this->assertSame(2, self::$loads, 'middleware instances cannot be cached');
        $this->assertFileDoesNotExist($this->cacheFile);
        $this->assertSame(['yes'], $this->response('GET', '/admin/users/1')->getHeader('X-Middleware'));
    }

    #[Test]
    public function test_routes_with_middleware_class_names_are_cached_with_their_middleware(): void
    {
        $this->writeRoutes(<<<'PHP'
            Router::group('/admin', function ($group) {
                $group->middleware(\Dromos\Tests\LoadRoutesTestMiddleware::class);
                $group->get('/users/{id}', [CONTROLLER::class, 'show']);
            });
            Router::Get('/public', [CONTROLLER::class, 'store']);
            PHP);

        $this->load();
        $this->load();

        $this->assertSame(1, self::$loads);
        $this->assertSame(['yes'], $this->response('GET', '/admin/users/1')->getHeader('X-Middleware'));
        $this->assertSame([], $this->response('GET', '/public')->getHeader('X-Middleware'));
    }

    #[Test]
    public function test_routes_registered_before_loading_are_kept_and_win_by_order(): void
    {
        $this->writeRoutes("Router::Get('/users/{id}', [CONTROLLER::class, 'show']);");
        $this->load();

        Router::reset();
        Router::Get('/users/me', fn(Response $r) => $r->json(['me' => true]));
        Router::loadRoutes($this->routesFile, $this->cacheFile);

        $this->assertSame(2, self::$loads, 'with routes already registered the cache is not used');
        $this->assertSame(['me' => true], $this->body('GET', '/users/me'));
        $this->assertSame(['id' => '7'], $this->body('GET', '/users/7'));
    }

    #[Test]
    public function test_routes_added_after_a_cached_load_are_dispatched_too(): void
    {
        $this->writeRoutes("Router::Get('/users/{id}', [CONTROLLER::class, 'show']);");
        $this->load();
        $this->load();

        Router::Get('/health', fn(Response $r) => $r->json(['ok' => true]));

        $this->assertSame(['ok' => true], $this->body('GET', '/health'));
        $this->assertSame(['id' => '7'], $this->body('GET', '/users/7'));
    }

    #[Test]
    public function test_a_corrupt_cache_file_is_rebuilt(): void
    {
        $this->writeRoutes("Router::Get('/users/{id}', [CONTROLLER::class, 'show']);");
        file_put_contents($this->cacheFile, '<?php this is not php');

        $this->load();
        $this->load();

        $this->assertSame(1, self::$loads, 'rebuilt by the first request, used by the second');
        $this->assertSame(['id' => '7'], $this->body('GET', '/users/7'));
    }

    #[Test]
    public function test_an_unwritable_cache_never_fails_a_request(): void
    {
        $this->writeRoutes("Router::Get('/users/{id}', [CONTROLLER::class, 'show']);");

        Router::reset();
        Router::loadRoutes($this->routesFile, $this->temporaryDirectory() . '/missing/dir/routes.cache.php');

        $this->assertSame(['id' => '7'], $this->body('GET', '/users/7'));
    }

    #[Test]
    public function test_a_missing_routes_file_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Router::loadRoutes($this->temporaryDirectory() . '/nope.php', $this->cacheFile);
    }
}

final class LoadRoutesTestController
{
    public function show(Response $response, string $id): ResponseInterface
    {
        return $response->json(['id' => $id]);
    }

    public function store(Response $response): ResponseInterface
    {
        return $response->json(['stored' => true]);
    }
}

final class LoadRoutesTestMiddleware implements \Dromos\Http\Middleware\MiddlewareInterface
{
    public function handle(
        \Dromos\Http\Message\ServerRequestInterface $request,
        \Dromos\Http\Middleware\RequestHandlerInterface $next
    ): ResponseInterface {
        return $next->handle($request)->withHeader('X-Middleware', 'yes');
    }
}
