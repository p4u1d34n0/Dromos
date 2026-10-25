<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Response;
use Dromos\Router;
use Dromos\Routing\ControllerResolverInterface;
use Dromos\Tests\Support\RouterTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Router::class)]
final class RouterMiddlewareClassTest extends RouterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CountedMiddleware::$created = 0;
    }

    #[Test]
    public function test_middleware_given_by_class_name_runs_in_order_with_instances(): void
    {
        Router::group('/api', function ($group) {
            $group->middleware(CountedMiddleware::class, new TagMiddleware('instance'));
            $group->get('/items/{id}', fn(Response $res, string $id) => $res->json(['id' => $id]));
        });

        $response = $this->response('GET', '/api/items/3');

        $this->assertSame(['id' => '3'], $this->decode($response));
        $this->assertSame(['instance', 'counted'], $response->getHeader('X-Tag'), 'outermost middleware runs first, so it tags last');
    }

    #[Test]
    public function test_middleware_classes_are_built_on_first_use_and_shared(): void
    {
        Router::group('/a', function ($group) {
            $group->middleware(CountedMiddleware::class);
            $group->get('/one', fn(Response $res) => $res->json([]));
            $group->get('/two', fn(Response $res) => $res->json([]));
        });
        Router::Get('/plain', fn(Response $res) => $res->json([]));

        $this->response('GET', '/plain');
        $this->assertSame(0, CountedMiddleware::$created, 'not built for routes that do not use it');

        $this->response('GET', '/a/one');
        $this->response('GET', '/a/two');
        $this->response('GET', '/a/one');
        $this->assertSame(1, CountedMiddleware::$created);
    }

    #[Test]
    public function test_middleware_classes_come_from_the_controller_resolver(): void
    {
        Router::setControllerResolver(new class implements ControllerResolverInterface {
            public function resolve(string $class): object
            {
                return $class === TagMiddleware::class ? new TagMiddleware('from-container') : new $class();
            }
        });
        Router::Get('/x', fn(Response $res) => $res->json([]));
        Router::setRouteMiddleware('GET', '/x', [TagMiddleware::class]);

        $this->assertSame(['from-container'], $this->response('GET', '/x')->getHeader('X-Tag'));
    }

    #[Test]
    public function test_a_class_that_is_not_middleware_is_rejected_at_registration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\stdClass::class);

        Router::setRouteMiddleware('GET', '/x', [\stdClass::class]);
    }

    #[Test]
    public function test_an_unknown_class_is_rejected_at_registration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Router::group('/g', function ($group) {
            $group->middleware('No\\Such\\Middleware');
            $group->get('/x', fn(Response $res) => $res->json([]));
        });
    }

    #[Test]
    public function test_a_resolver_returning_something_else_is_a_500(): void
    {
        Router::setControllerResolver(new class implements ControllerResolverInterface {
            public function resolve(string $class): object
            {
                return new \stdClass();
            }
        });
        Router::Get('/x', fn(Response $res) => $res->json([]));
        Router::setRouteMiddleware('GET', '/x', [CountedMiddleware::class]);

        $this->assertSame(500, $this->statusOf('GET', '/x'));
    }
}

final class CountedMiddleware implements MiddlewareInterface
{
    public static int $created = 0;

    public function __construct()
    {
        self::$created++;
    }

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withAddedHeader('X-Tag', 'counted');
    }
}

final class TagMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $tag = 'tag')
    {
    }

    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withAddedHeader('X-Tag', $this->tag);
    }
}
