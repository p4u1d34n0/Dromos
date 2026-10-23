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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Router::class)]
final class RouterMatchingTest extends RouterTestCase
{
    #[Test]
    public function test_it_matches_static_routes(): void
    {
        Router::Get('/', $this->label('root'));
        Router::Get('/users', $this->label('users'));

        $this->assertSame(['route' => 'root'], $this->body('GET', '/'));
        $this->assertSame(['route' => 'users'], $this->body('GET', '/users'));
    }

    #[Test]
    public function test_it_extracts_route_parameters(): void
    {
        Router::Get('/users/{id}', $this->label('user'));
        Router::Get('/users/{id}/posts/{post}', $this->label('post'));
        Router::Get('/files/{name}.json', $this->label('file'));

        $this->assertSame(['route' => 'user', 'id' => '42'], $this->body('GET', '/users/42'));
        $this->assertSame(['route' => 'post', 'id' => '42', 'post' => '7'], $this->body('GET', '/users/42/posts/7'));
        $this->assertSame(['route' => 'file', 'name' => 'report'], $this->body('GET', '/files/report.json'));
    }

    #[Test]
    public function test_it_ignores_the_query_string(): void
    {
        Router::Get('/users/{id}', $this->label('user'));

        $this->assertSame(['route' => 'user', 'id' => '9'], $this->body('GET', '/users/9?expand=posts'));
    }

    #[Test]
    public function test_parameters_do_not_span_slashes(): void
    {
        Router::Get('/users/{id}', $this->label('user'));

        $this->assertSame(404, $this->statusOf('GET', '/users/42/extra'));
        $this->assertSame(404, $this->statusOf('GET', '/users/'));
    }

    #[Test]
    public function test_literal_text_in_routes_is_not_treated_as_regex(): void
    {
        Router::Get('/files/{name}.json', $this->label('file'));
        Router::Get('/v1.0/status', $this->label('status'));

        $this->assertSame(404, $this->statusOf('GET', '/files/reportXjson'));
        $this->assertSame(404, $this->statusOf('GET', '/v1x0/status'));
        $this->assertSame(['route' => 'status'], $this->body('GET', '/v1.0/status'));
    }

    #[Test]
    public function test_it_routes_by_method(): void
    {
        Router::Get('/users', $this->label('list'));
        Router::Post('/users', $this->label('create'));
        Router::Put('/users/{id}', $this->label('update'));

        $this->assertSame(['route' => 'create'], $this->body('POST', '/users'));
        $this->assertSame(['route' => 'update', 'id' => '3'], $this->body('PUT', '/users/3'));
    }

    #[Test]
    public function test_it_returns_404_for_unknown_paths(): void
    {
        Router::Get('/users/{id}', $this->label('user'));

        $this->assertSame(404, $this->statusOf('GET', '/nope'));
        $this->assertSame(404, $this->statusOf('PATCH', '/nope'));
    }

    #[Test]
    public function test_it_returns_405_with_the_allowed_methods(): void
    {
        Router::Get('/users/{id}', $this->label('show'));
        Router::Put('/users/{id}', $this->label('update'));
        Router::Post('/users', $this->label('create'));

        $response = $this->response('DELETE', '/users/1');
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame(['GET', 'PUT'], $this->decode($response)['details']['available_methods']);

        $response = $this->response('GET', '/users');
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame(['POST'], $this->decode($response)['details']['available_methods']);
    }

    #[Test]
    public function test_an_earlier_parameterised_route_wins_over_a_later_static_route(): void
    {
        Router::Get('/users/{id}', $this->label('param'));
        Router::Get('/users/me', $this->label('static'));

        $this->assertSame(['route' => 'param', 'id' => 'me'], $this->body('GET', '/users/me'));
    }

    #[Test]
    public function test_an_earlier_static_route_wins_over_a_later_parameterised_route(): void
    {
        Router::Get('/teams/all', $this->label('static'));
        Router::Get('/teams/{id}', $this->label('param'));

        $this->assertSame(['route' => 'static'], $this->body('GET', '/teams/all'));
        $this->assertSame(['route' => 'param', 'id' => '5'], $this->body('GET', '/teams/5'));
    }

    #[Test]
    public function test_the_first_of_two_overlapping_parameterised_routes_wins(): void
    {
        Router::Get('/a/{x}', $this->label('first'));
        Router::Get('/a/{y}', $this->label('second'));

        $this->assertSame(['route' => 'first', 'x' => '1'], $this->body('GET', '/a/1'));
    }

    #[Test]
    public function test_a_repeated_placeholder_must_match_the_same_value(): void
    {
        Router::Get('/compare/{v}/{v}', $this->label('same'));
        Router::Get('/compare/{a}/{b}', $this->label('different'));

        $this->assertSame(['route' => 'same', 'v' => '1'], $this->body('GET', '/compare/1/1'));
        $this->assertSame(['route' => 'different', 'a' => '1', 'b' => '2'], $this->body('GET', '/compare/1/2'));
    }

    #[Test]
    public function test_a_path_equal_to_a_route_template_matches_that_route_without_parameters(): void
    {
        Router::Get('/users/{id}', $this->label('user'));

        $this->assertSame(['route' => 'user'], $this->body('GET', '/users/{id}'));
    }

    #[Test]
    public function test_registration_order_holds_across_large_route_tables(): void
    {
        // More than one combined regex's worth of parameterised routes
        for ($i = 0; $i < 120; $i++) {
            Router::Get("/r$i/{id}", $this->label("r$i"));
        }
        Router::Get('/r119/latest', $this->label('shadowed'));
        Router::Get('/{section}/{id}', $this->label('catch-all'));

        $this->assertSame(['route' => 'r0', 'id' => '1'], $this->body('GET', '/r0/1'));
        $this->assertSame(['route' => 'r75', 'id' => '1'], $this->body('GET', '/r75/1'));
        $this->assertSame(['route' => 'r119', 'id' => 'latest'], $this->body('GET', '/r119/latest'));
        $this->assertSame(['route' => 'catch-all', 'section' => 'other', 'id' => '1'], $this->body('GET', '/other/1'));
    }

    #[Test]
    public function test_routes_added_after_a_dispatch_are_matched(): void
    {
        Router::Get('/before', $this->label('before'));
        $this->assertSame(200, $this->statusOf('GET', '/before'));

        Router::Get('/after/{id}', $this->label('after'));
        Router::Post('/before', $this->label('post'));

        $this->assertSame(['route' => 'after', 'id' => '3'], $this->body('GET', '/after/3'));
        $this->assertSame(['route' => 'post'], $this->body('POST', '/before'));
    }

    #[Test]
    public function test_reset_clears_matched_routes(): void
    {
        Router::Get('/before', $this->label('before'));
        $this->assertSame(200, $this->statusOf('GET', '/before'));

        Router::reset();

        $this->assertSame(404, $this->statusOf('GET', '/before'));
    }

    #[Test]
    public function test_group_middleware_runs_for_static_and_parameterised_routes(): void
    {
        $tag = new class implements MiddlewareInterface {
            public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request)->withHeader('X-Tag', 'yes');
            }
        };
        Router::group('/api', function ($group) use ($tag) {
            $group->middleware($tag);
            $group->get('/items', $this->label('items'));
            $group->get('/items/{id}', $this->label('item'));
        });

        $this->assertSame('yes', $this->response('GET', '/api/items')->getHeaderLine('X-Tag'));
        $response = $this->response('GET', '/api/items/8');
        $this->assertSame('yes', $response->getHeaderLine('X-Tag'));
        $this->assertSame(['route' => 'item', 'id' => '8'], json_decode((string) $response->getBody(), true));
    }

    #[Test]
    public function test_global_middleware_wraps_every_response_including_errors(): void
    {
        Router::Get('/items', $this->label('items'));
        $router = new Router();
        $router->addMiddleware(new class implements MiddlewareInterface {
            public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request)->withHeader('X-Global', 'yes');
            }
        });

        $this->assertSame('yes', $router->handle(Request::create('GET', '/items'))->getHeaderLine('X-Global'));
        $notFound = $router->handle(Request::create('GET', '/nope'));
        $this->assertSame(404, $notFound->getStatusCode());
        $this->assertSame('yes', $notFound->getHeaderLine('X-Global'));
    }

    #[Test]
    public function test_it_matches_routes_loaded_from_the_route_cache(): void
    {
        $cacheFile = sys_get_temp_dir() . '/dromos_route_cache_' . uniqid() . '.json';

        try {
            Router::enableCache($cacheFile);
            Router::Get('/items/{id}', [RouterMatchingTestController::class, 'show']);
            $this->assertSame(['controller' => '5'], $this->body('GET', '/items/5'));

            Router::reset();
            $this->assertSame(404, $this->statusOf('GET', '/items/6'));

            Router::enableCache($cacheFile);
            $this->assertSame(['controller' => '6'], $this->body('GET', '/items/6'));
        } finally {
            @unlink($cacheFile);
        }
    }

    // ────────────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────────────

    /** A route handler that echoes its label and the matched parameters. */
    private function label(string $route): \Closure
    {
        return static fn(Request $request, Response $response) =>
            $response->json(['route' => $route] + $request->getAttributes());
    }

}

final class RouterMatchingTestController
{
    public function show(Request $request, Response $response, string $id): ResponseInterface
    {
        return $response->json(['controller' => $id]);
    }
}
