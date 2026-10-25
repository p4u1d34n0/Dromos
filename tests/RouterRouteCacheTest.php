<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Router;
use Dromos\Tests\Support\RouterTestCase;
use Dromos\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Router::class)]
final class RouterRouteCacheTest extends RouterTestCase
{
    use TemporaryDirectory;

    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = $this->temporaryDirectory() . '/routes.php';
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeTemporaryDirectory();
    }

    #[Test]
    public function test_closure_and_controller_routes_work_with_the_cache_enabled(): void
    {
        $this->request(function () {
            Router::Get('/closure/{id}', fn(Response $res, string $id) => $res->json(['closure' => $id]));
            Router::Get('/controller/{id}', [RouterRouteCacheTestController::class, 'show']);
        });

        $this->assertSame(['closure' => '1'], $this->body('GET', '/closure/1'));
        $this->assertSame(['controller' => '2'], $this->body('GET', '/controller/2'));
        $this->assertFileExists($this->cacheFile);
    }

    #[Test]
    public function test_a_warm_cache_is_reused_without_rewriting_it(): void
    {
        $routes = fn() => Router::Get('/items/{id}', [RouterRouteCacheTestController::class, 'show']);
        $this->request($routes);
        $this->assertSame(200, $this->statusOf('GET', '/items/1'));
        touch($this->cacheFile, time() - 3600);
        clearstatcache();
        $writtenAt = filemtime($this->cacheFile);

        $this->request($routes);
        $this->assertSame(['controller' => '2'], $this->body('GET', '/items/2'));

        clearstatcache();
        $this->assertSame($writtenAt, filemtime($this->cacheFile));
    }

    #[Test]
    public function test_changed_routes_take_effect_immediately(): void
    {
        $this->request(function () {
            Router::Get('/old', [RouterRouteCacheTestController::class, 'index']);
            Router::Get('/kept', [RouterRouteCacheTestController::class, 'index']);
        });
        $this->assertSame(200, $this->statusOf('GET', '/old'));

        $this->request(function () {
            Router::Get('/kept', [RouterRouteCacheTestController::class, 'index']);
            Router::Get('/new', [RouterRouteCacheTestController::class, 'index']);
        });

        $this->assertSame(404, $this->statusOf('GET', '/old'));
        $this->assertSame(200, $this->statusOf('GET', '/kept'));
        $this->assertSame(200, $this->statusOf('GET', '/new'));
    }

    #[Test]
    public function test_group_middleware_runs_with_the_cache_enabled(): void
    {
        $this->request(function () {
            Router::group('/api', function ($group) {
                $group->middleware(new class implements MiddlewareInterface {
                    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
                    {
                        return (new Response())->withStatus(401);
                    }
                });
                $group->get('/secret', [RouterRouteCacheTestController::class, 'index']);
            });
        });

        $this->assertSame(401, $this->statusOf('GET', '/api/secret'));
    }

    #[Test]
    public function test_clear_cache_deletes_the_file_and_it_is_rebuilt(): void
    {
        $this->request(fn() => Router::Get('/a', [RouterRouteCacheTestController::class, 'index']));
        $this->statusOf('GET', '/a');

        Router::clearCache();
        $this->assertFileDoesNotExist($this->cacheFile);

        $this->assertSame(200, $this->statusOf('GET', '/a'));
        $this->assertFileExists($this->cacheFile);
    }

    #[Test]
    public function test_cached_routes_lists_the_stored_controller_routes(): void
    {
        $this->request(function () {
            Router::Get('/a', [RouterRouteCacheTestController::class, 'index']);
            Router::Post('/b', fn(Response $res) => $res);
        });
        $this->statusOf('GET', '/a');

        $this->assertSame(['GET' => ['/a' => [RouterRouteCacheTestController::class, 'index']]], Router::cachedRoutes());
    }

    #[Test]
    public function test_dispatching_cached_routes_without_registering_them_still_works_but_is_deprecated(): void
    {
        $this->request(fn() => Router::Get('/items/{id}', [RouterRouteCacheTestController::class, 'show']));
        $this->statusOf('GET', '/items/1');

        $this->request(fn() => null);
        $deprecations = [];
        set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;
            return true;
        }, E_USER_DEPRECATED);
        try {
            $body = $this->body('GET', '/items/6');
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['controller' => '6'], $body);
        $this->assertCount(1, $deprecations);
        $this->assertStringContainsString('without registering them is deprecated', $deprecations[0]);
    }

    /**
     * Start a new "request": clear the router, enable the cache and register routes.
     */
    private function request(\Closure $registerRoutes): void
    {
        Router::reset();
        Router::enableCache($this->cacheFile);
        $registerRoutes();
    }
}

final class RouterRouteCacheTestController
{
    public function index(Response $response): ResponseInterface
    {
        return $response->json([]);
    }

    public function show(Request $request, Response $response, string $id): ResponseInterface
    {
        return $response->json(['controller' => $id]);
    }
}
