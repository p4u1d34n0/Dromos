<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Router::class)]
final class RouterHandlerArgumentsTest extends TestCase
{
    private string $errorLog;

    protected function setUp(): void
    {
        Router::reset();
        $this->errorLog = (string) ini_get('error_log');
        ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
        Router::reset();
        ini_set('error_log', $this->errorLog);
    }

    #[Test]
    public function test_it_injects_request_and_response_by_type_in_any_order(): void
    {
        Router::Get('/a', fn(Response $res, ServerRequestInterface $req) => $res->json(['path' => $req->getUri()->getPath()]));

        $this->assertSame(['path' => '/a'], $this->body('GET', '/a'));
        $this->assertSame(['path' => '/a'], $this->body('GET', '/a'));
    }

    #[Test]
    public function test_it_passes_route_parameters_by_name(): void
    {
        Router::Get('/posts/{post}/comments/{comment}', fn(string $comment, Response $res, string $post) =>
            $res->json([$post, $comment]));

        $this->assertSame(['1', '2'], $this->body('GET', '/posts/1/comments/2'));
        $this->assertSame(['3', '4'], $this->body('GET', '/posts/3/comments/4'));
    }

    #[Test]
    public function test_one_handler_on_several_routes_gets_each_routes_parameters(): void
    {
        $handler = fn(Response $res, string $id = 'none') => $res->json(['id' => $id]);
        Router::Get('/items', $handler);
        Router::Get('/items/{id}', $handler);

        $this->assertSame(['id' => '5'], $this->body('GET', '/items/5'));
        $this->assertSame(['id' => 'none'], $this->body('GET', '/items'));
        $this->assertSame(['id' => '6'], $this->body('GET', '/items/6'));
    }

    #[Test]
    public function test_object_defaults_are_created_fresh_for_each_call(): void
    {
        Router::Get('/count', function (Response $res, \ArrayObject $seen = new \ArrayObject()) {
            $seen[] = 'call';
            return $res->json(['count' => count($seen)]);
        });

        $this->assertSame(['count' => 1], $this->body('GET', '/count'));
        $this->assertSame(['count' => 1], $this->body('GET', '/count'));
    }

    #[Test]
    public function test_an_unresolvable_parameter_returns_500(): void
    {
        Router::Get('/broken', fn(Response $res, string $missing) => $res->json([]));

        $this->assertSame(500, $this->dispatch('GET', '/broken')->getStatusCode());
        $this->assertSame(500, $this->dispatch('GET', '/broken')->getStatusCode());
    }

    #[Test]
    public function test_new_handlers_after_reset_get_their_own_plans(): void
    {
        Router::Get('/x/{id}', fn(Response $res, string $id) => $res->json(['id' => $id]));
        $this->assertSame(['id' => '1'], $this->body('GET', '/x/1'));

        Router::reset();
        Router::Get('/x/{slug}', fn(string $slug, Request $req, Response $res) => $res->json(['slug' => $slug]));

        $this->assertSame(['slug' => 'two'], $this->body('GET', '/x/two'));
    }

    #[Test]
    public function test_it_resolves_controller_method_parameters(): void
    {
        Router::Get('/users/{id}', [RouterHandlerArgumentsTestController::class, 'show']);
        Router::Get('/users', [RouterHandlerArgumentsTestController::class, 'index']);

        $this->assertSame(['id' => '7', 'path' => '/users/7'], $this->body('GET', '/users/7'));
        $this->assertSame(['page' => 1], $this->body('GET', '/users'));
        $this->assertSame(['id' => '8', 'path' => '/users/8'], $this->body('GET', '/users/8'));
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────────────

    private function dispatch(string $method, string $uri): ResponseInterface
    {
        return (new Router())->handle(Request::create($method, $uri));
    }

    /** @return array<mixed> */
    private function body(string $method, string $uri): array
    {
        $response = $this->dispatch($method, $uri);
        $this->assertSame(200, $response->getStatusCode(), "$method $uri: " . $response->getBody());
        return json_decode((string) $response->getBody(), true);
    }
}

final class RouterHandlerArgumentsTestController
{
    public function show(Request $request, Response $response, string $id): ResponseInterface
    {
        return $response->json(['id' => $id, 'path' => $request->getUri()->getPath()]);
    }

    public function index(Response $response, int $page = 1): ResponseInterface
    {
        return $response->json(['page' => $page]);
    }
}
