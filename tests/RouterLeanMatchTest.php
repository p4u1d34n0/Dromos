<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Response;
use Dromos\Router;
use Dromos\Tests\Support\RouterTestCase;
use Dromos\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Router::class)]
final class RouterLeanMatchTest extends RouterTestCase
{
    use TemporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        LeanMatchTestMiddleware::$runs = 0;
        RouterLeanMatchTest::$loads = 0;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeTemporaryDirectory();
    }

    /** How many times the routes file in the cache test has run. */
    public static int $loads = 0;

    #[Test]
    public function test_a_static_route_gives_its_handler_with_no_params(): void
    {
        Router::Get('/users', [LeanMatchTestController::class, 'index']);

        $this->assertSame([200, [LeanMatchTestController::class, 'index'], [], []], Router::match('GET', '/users'));
    }

    #[Test]
    public function test_a_route_with_placeholders_gives_their_values(): void
    {
        Router::Get('/users/{id}/posts/{post}', [LeanMatchTestController::class, 'show']);

        $this->assertSame(
            [200, [LeanMatchTestController::class, 'show'], ['id' => '7', 'post' => 'hello'], []],
            Router::match('GET', '/users/7/posts/hello')
        );
    }

    #[Test]
    public function test_a_closure_route_gives_the_same_closure(): void
    {
        $handler = fn(Response $r) => $r->json([]);
        Router::Get('/hi', $handler);

        $route = Router::match('GET', '/hi');

        $this->assertSame(200, $route[0]);
        $this->assertSame($handler, $route[1]);
    }

    #[Test]
    public function test_a_path_with_no_route_is_a_404(): void
    {
        Router::Get('/users/{id}', [LeanMatchTestController::class, 'show']);

        $this->assertSame([404], Router::match('GET', '/posts/7'));
        $this->assertSame([404], Router::match('GET', '/users/7/extra'));
    }

    #[Test]
    public function test_a_path_routed_only_under_other_methods_is_a_405_listing_them_in_order(): void
    {
        Router::Delete('/users/{id}', [LeanMatchTestController::class, 'show']);
        Router::Get('/users/{id}', [LeanMatchTestController::class, 'show']);
        Router::Post('/users', [LeanMatchTestController::class, 'index']);

        $this->assertSame([405, ['DELETE', 'GET']], Router::match('PUT', '/users/7'));
        $this->assertSame([405, ['POST']], Router::match('GET', '/users'));
    }

    #[Test]
    public function test_a_routes_middleware_is_returned_but_not_run(): void
    {
        $instance = new LeanMatchTestMiddleware();
        Router::group('/admin', function ($group) use ($instance) {
            $group->middleware(LeanMatchTestMiddleware::class);
            $group->get('/users/{id}', [LeanMatchTestController::class, 'show']);
        });
        Router::Get('/public', [LeanMatchTestController::class, 'index']);
        Router::setRouteMiddleware('GET', '/public', [$instance]);

        $this->assertSame(
            [200, [LeanMatchTestController::class, 'show'], ['id' => '3'], [LeanMatchTestMiddleware::class]],
            Router::match('GET', '/admin/users/3')
        );
        $this->assertSame([$instance], Router::match('GET', '/public')[3]);
        $this->assertSame(0, LeanMatchTestMiddleware::$runs);
    }

    #[Test]
    public function test_it_answers_every_path_as_handle_does(): void
    {
        // Registration order decides between overlapping routes, in both entry points
        Router::Get('/users/{id}', [LeanMatchTestController::class, 'show']);
        Router::Get('/users/me', [LeanMatchTestController::class, 'index']);
        Router::Get('/files/report', [LeanMatchTestController::class, 'index']);
        Router::Get('/files/{name}', [LeanMatchTestController::class, 'show']);
        Router::Post('/users', [LeanMatchTestController::class, 'index']);

        $paths = ['/users/me', '/users/7', '/files/report', '/files/x', '/users', '/nope', '/users/7/x'];
        foreach (['GET', 'POST', 'PUT'] as $method) {
            foreach ($paths as $path) {
                $route = Router::match($method, $path);
                $response = $this->response($method, $path);

                $this->assertSame($response->getStatusCode(), $route[0], "$method $path");
                if ($route[0] === 200) {
                    [, [$class, $action], $params] = $route;
                    $this->assertSame(
                        (string) $response->getBody(),
                        (string) (new $class())->{$action}(new Response(), ...$params)->getBody(),
                        "$method $path"
                    );
                }
            }
        }
    }

    #[Test]
    public function test_routes_added_after_a_match_are_found(): void
    {
        Router::Get('/a', [LeanMatchTestController::class, 'index']);
        $this->assertSame([404], Router::match('GET', '/b'));

        Router::Get('/b', [LeanMatchTestController::class, 'index']);

        $this->assertSame(200, Router::match('GET', '/b')[0]);
    }

    #[Test]
    public function test_a_static_route_registered_again_gives_its_new_handler(): void
    {
        Router::Get('/a', [LeanMatchTestController::class, 'index']);
        Router::match('GET', '/a');

        Router::Get('/a', [LeanMatchTestController::class, 'show']);

        $this->assertSame([LeanMatchTestController::class, 'show'], Router::match('GET', '/a')[1]);
    }

    #[Test]
    public function test_middleware_set_after_a_match_is_in_the_next_one(): void
    {
        Router::Get('/a', [LeanMatchTestController::class, 'index']);
        Router::match('GET', '/a');

        Router::setRouteMiddleware('GET', '/a', [LeanMatchTestMiddleware::class]);

        $this->assertSame([LeanMatchTestMiddleware::class], Router::match('GET', '/a')[3]);
        $this->assertSame(1, $this->runsOfMiddlewareFor('GET', '/a'), 'handle() runs it too');
    }

    #[Test]
    public function test_each_method_has_its_own_answer_for_a_static_path(): void
    {
        Router::Get('/a', [LeanMatchTestController::class, 'index']);
        Router::Post('/a', [LeanMatchTestController::class, 'show']);

        Router::match('GET', '/a');

        $this->assertSame([LeanMatchTestController::class, 'show'], Router::match('POST', '/a')[1]);
        $this->assertSame([405, ['GET', 'POST']], Router::match('PUT', '/a'));
    }

    private function runsOfMiddlewareFor(string $method, string $path): int
    {
        LeanMatchTestMiddleware::$runs = 0;
        $this->response($method, $path);
        return LeanMatchTestMiddleware::$runs;
    }

    #[Test]
    public function test_cached_routes_are_matched_without_running_the_routes_file(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents("$dir/routes.php", '<?php
            use Dromos\Router;
            \\' . self::class . '::$loads++;
            Router::group("/admin", function ($group) {
                $group->middleware(\\' . LeanMatchTestMiddleware::class . '::class);
                $group->get("/users/{id}", [\\' . LeanMatchTestController::class . '::class, "show"]);
            });');

        Router::loadRoutes("$dir/routes.php", "$dir/cache.php");
        Router::reset();
        Router::loadRoutes("$dir/routes.php", "$dir/cache.php");

        $this->assertSame(1, self::$loads, 'the second load reads the cache');
        $this->assertSame(
            [200, [LeanMatchTestController::class, 'show'], ['id' => '9'], [LeanMatchTestMiddleware::class]],
            Router::match('GET', '/admin/users/9')
        );
        $this->assertSame([405, ['GET']], Router::match('POST', '/admin/users/9'));
    }
}

final class LeanMatchTestController
{
    public function index(Response $response): ResponseInterface
    {
        return $response->json(['index' => true]);
    }

    public function show(Response $response, string $id = '', string $name = '', string $post = ''): ResponseInterface
    {
        return $response->json(['id' => $id, 'name' => $name, 'post' => $post]);
    }
}

final class LeanMatchTestMiddleware implements MiddlewareInterface
{
    public static int $runs = 0;

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        self::$runs++;
        return $next->handle($request);
    }
}
