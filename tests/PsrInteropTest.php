<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Http\Message\Stream;
use Dromos\Http\Message\UploadedFile;
use Dromos\Http\Middleware\MiddlewareInterface as DromosMiddleware;
use Dromos\Http\Middleware\RequestHandlerInterface as DromosHandler;
use Dromos\Http\PsrRequestHandler;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Http\Uri;
use Dromos\Router;
use Dromos\Routing\MiddlewarePipeline;
use Dromos\Tests\Support\RouterTestCase;
use Dromos\Tests\Support\TemporaryDirectory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest as NyholmServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Dromos with real PSR-7 and PSR-15 code: its messages used as PSR-7
 * messages, PSR-15 middleware run in its pipeline, and Nyholm's PSR-7
 * messages (another library's) passed through it.
 */
#[CoversClass(Router::class)]
#[CoversClass(MiddlewarePipeline::class)]
#[CoversClass(Request::class)]
#[CoversClass(Response::class)]
#[CoversClass(PsrRequestHandler::class)]
final class PsrInteropTest extends RouterTestCase
{
    use TemporaryDirectory;

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeTemporaryDirectory();
    }

    #[Test]
    public function test_dromos_messages_are_psr7_messages(): void
    {
        $this->assertInstanceOf(ServerRequestInterface::class, Request::create());
        $this->assertInstanceOf(RequestInterface::class, Request::create());
        $this->assertInstanceOf(ResponseInterface::class, new Response());
        $this->assertInstanceOf(MessageInterface::class, new Response());
        $this->assertInstanceOf(StreamInterface::class, new Stream());
        $this->assertInstanceOf(UriInterface::class, new Uri('/'));
        $this->assertInstanceOf(UploadedFileInterface::class, new UploadedFile(new Stream('x'), 1, UPLOAD_ERR_OK));
        $this->assertInstanceOf(RequestHandlerInterface::class, new PsrRequestHandler(new Router()));
    }

    #[Test]
    public function test_a_dromos_request_takes_another_librarys_stream_and_uri(): void
    {
        $factory = new Psr17Factory();

        $request = Request::create()
            ->withBody($factory->createStream('{"a":1}'))
            ->withUri($factory->createUri('https://example.com:8443/users?page=2'));

        $this->assertSame('{"a":1}', (string) $request->getBody());
        $this->assertSame('/users', $request->getUri()->getPath());
        $this->assertSame(['example.com:8443'], $request->getHeader('Host'));
    }

    #[Test]
    public function test_psr15_middleware_runs_as_an_instance_and_by_class_name(): void
    {
        $router = new Router();
        $router->addMiddleware(new PsrTagMiddleware('global'));
        Router::group('/api', function ($group) {
            $group->middleware(PsrTagMiddleware::class, new DromosTagMiddleware('dromos'));
            $group->get('/items/{id}', fn(Response $r, ServerRequestInterface $request, string $id) => $r->json([
                'id'   => $id,
                'tags' => $request->getAttribute('tags'),
            ]));
        });

        $response = $router->handle(Request::create('GET', '/api/items/3'));

        $this->assertSame(['id' => '3', 'tags' => ['global', 'psr', 'dromos']], $this->decode($response));
        $this->assertSame(['dromos', 'psr', 'global'], $response->getHeader('X-Tag'), 'the outermost middleware tags last');
    }

    #[Test]
    public function test_psr15_middleware_can_answer_without_calling_the_handler(): void
    {
        Router::Get('/secret', fn(Response $r) => $r->json(['secret' => true]));
        Router::setRouteMiddleware('GET', '/secret', [new PsrDenyMiddleware()]);

        $response = $this->response('GET', '/secret');

        $this->assertInstanceOf(Response::class, $response, "Nyholm's response is copied into a Dromos one");
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Not you', $response->getReasonPhrase());
        $this->assertSame(['text/plain'], $response->getHeader('Content-Type'));
        $this->assertSame(['a', 'b'], $response->getHeader('X-Multi'));
        $this->assertSame('denied', (string) $response->getBody());
        $this->assertSame('2.0', $response->getProtocolVersion());
    }

    #[Test]
    public function test_a_request_another_library_built_reaches_dromos_middleware_and_the_handler_intact(): void
    {
        $seen = null;
        Router::Post('/upload', function (Response $r, \Dromos\Http\Message\ServerRequestInterface $request) use (&$seen) {
            $seen = $request;
            return $r->json([]);
        });
        Router::setRouteMiddleware('POST', '/upload', [new PsrReplaceRequestMiddleware(), new DromosTagMiddleware('after')]);

        $this->assertSame(200, $this->statusOf('POST', '/upload'));

        $this->assertInstanceOf(Request::class, $seen);
        $this->assertSame('POST', $seen->getMethod());
        $this->assertSame('/upload', $seen->getUri()->getPath());
        $this->assertSame(['nyholm'], $seen->getHeader('X-From'));
        $this->assertSame(['page' => '2'], $seen->getQueryParams());
        $this->assertSame(['session' => 'abc'], $seen->getCookieParams());
        $this->assertSame(['name' => 'Ada'], $seen->getParsedBody());
        $this->assertSame('raw body', (string) $seen->getBody());
        $this->assertSame('nyholm', $seen->getAttribute('built-by'));
        $this->assertSame(['after'], $seen->getAttribute('tags'), 'the Dromos middleware after it saw the copy');
        $this->assertSame(['SERVER_NAME' => 'example.com'], $seen->getServerParams());
        $this->assertSame('1.0', $seen->getProtocolVersion());
    }

    #[Test]
    public function test_a_request_another_library_built_reaches_the_handler_straight_after_psr15_middleware(): void
    {
        Router::Get('/users/{id}', fn(Response $r, \Dromos\Http\Message\ServerRequestInterface $request, string $id) => $r->json([
            'id'       => $id,
            'built-by' => $request->getAttribute('built-by'),
        ]));
        Router::setRouteMiddleware('GET', '/users/{id}', [new PsrReplaceRequestMiddleware()]);

        $this->assertSame(['id' => '5', 'built-by' => 'nyholm'], $this->body('GET', '/users/5'));
    }

    #[Test]
    public function test_the_router_handles_another_librarys_request_through_the_psr15_adapter(): void
    {
        Router::Get('/users/{id}', fn(Response $r, \Dromos\Http\Message\ServerRequestInterface $request, string $id) => $r->json([
            'id'   => $id,
            'host' => $request->getHeaderLine('Host'),
        ]));

        $response = (new PsrRequestHandler(new Router()))->handle(new NyholmServerRequest('GET', 'https://example.com/users/9'));

        $this->assertSame(['id' => '9', 'host' => 'example.com'], $this->decode($response));
    }

    #[Test]
    public function test_handlers_can_ask_for_psr7_types_and_return_another_librarys_response(): void
    {
        Router::Get('/hello/{name}', fn(ServerRequestInterface $request, ResponseInterface $response, string $name) => (new Psr17Factory())
            ->createResponse(201)
            ->withHeader('X-Method', $request->getMethod())
            ->withHeader('X-Given', $response::class)
            ->withBody((new Psr17Factory())->createStream("hello $name")));

        $response = $this->response('GET', '/hello/ada');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['GET'], $response->getHeader('X-Method'));
        $this->assertSame([Response::class], $response->getHeader('X-Given'));
        $this->assertSame('hello ada', (string) $response->getBody());
    }

    #[Test]
    public function test_routes_with_psr15_middleware_by_class_name_are_cached(): void
    {
        $dir = $this->temporaryDirectory();
        file_put_contents("$dir/routes.php", '<?php
            \\' . PsrTagMiddleware::class . '::$loads++;
            Dromos\\Router::group("/api", function ($group) {
                $group->middleware(\\' . PsrTagMiddleware::class . '::class);
                $group->get("/items/{id}", [\\' . PsrInteropController::class . '::class, "show"]);
            });');
        PsrTagMiddleware::$loads = 0;

        Router::loadRoutes("$dir/routes.php", "$dir/cache.php");
        Router::reset();
        Router::loadRoutes("$dir/routes.php", "$dir/cache.php");

        $this->assertSame(1, PsrTagMiddleware::$loads, 'the second load reads the cache');
        $this->assertSame(['psr'], $this->response('GET', '/api/items/1')->getHeader('X-Tag'));
    }

    #[Test]
    public function test_anything_else_is_refused_as_middleware(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(MiddlewareInterface::class);

        Router::setRouteMiddleware('GET', '/a', [\stdClass::class]);
    }

    #[Test]
    public function test_from_psr_returns_a_dromos_message_as_it_is(): void
    {
        $request = Request::create();
        $response = new Response();

        $this->assertSame($request, Request::fromPsr($request));
        $this->assertSame($response, Response::fromPsr($response));
    }

    #[Test]
    public function test_a_dromos_handler_without_middleware_is_used_as_it_is(): void
    {
        $handler = new class implements DromosHandler {
            public function handle(\Dromos\Http\Message\ServerRequestInterface $request): \Dromos\Http\Message\ResponseInterface
            {
                return (new Response())->text($request->getMethod());
            }
        };

        $this->assertSame($handler, MiddlewarePipeline::wrap([], $handler));
        $this->assertSame('PUT', (string) MiddlewarePipeline::wrap([new PsrTagMiddleware('x')], $handler)->handle(Request::create('PUT'))->getBody());
    }
}

final class PsrInteropController
{
    public function show(Response $response, string $id): \Dromos\Http\Message\ResponseInterface
    {
        return $response->json(['id' => $id]);
    }
}

/** PSR-15 middleware that adds its tag to the request's "tags" attribute and the response's X-Tag header. */
final class PsrTagMiddleware implements MiddlewareInterface
{
    public static int $loads = 0;

    public function __construct(private readonly string $tag = 'psr')
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $tags = $request->getAttribute('tags', []);
        $response = $handler->handle($request->withAttribute('tags', [...$tags, $this->tag]));
        return $response->withAddedHeader('X-Tag', $this->tag);
    }
}

/** The same as PsrTagMiddleware, as Dromos middleware. */
final class DromosTagMiddleware implements DromosMiddleware
{
    public function __construct(private readonly string $tag)
    {
    }

    public function handle(\Dromos\Http\Message\ServerRequestInterface $request, DromosHandler $handler): \Dromos\Http\Message\ResponseInterface
    {
        $tags = $request->getAttribute('tags', []);
        $response = $handler->handle($request->withAttribute('tags', [...$tags, $this->tag]));
        return $response->withAddedHeader('X-Tag', $this->tag);
    }
}

/** PSR-15 middleware that answers with its own Nyholm response. */
final class PsrDenyMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $factory = new Psr17Factory();
        return $factory->createResponse(403, 'Not you')
            ->withProtocolVersion('2.0')
            ->withHeader('Content-Type', 'text/plain')
            ->withHeader('X-Multi', ['a', 'b'])
            ->withBody($factory->createStream('denied'));
    }
}

/** PSR-15 middleware that passes on a Nyholm request built from the one it was given. */
final class PsrReplaceRequestMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $replacement = (new NyholmServerRequest($request->getMethod(), $request->getUri(), ['X-From' => 'nyholm'], 'raw body', '1.0', ['SERVER_NAME' => 'example.com']))
            ->withQueryParams(['page' => '2'])
            ->withCookieParams(['session' => 'abc'])
            ->withParsedBody(['name' => 'Ada'])
            ->withAttribute('built-by', 'nyholm');
        return $handler->handle($replacement);
    }
}
