<?php

declare(strict_types=1);

namespace Dromos\Tests\Routing;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Routing\MiddlewarePipeline;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MiddlewarePipeline::class)]
final class MiddlewarePipelineTest extends TestCase
{
    #[Test]
    public function test_middleware_run_in_order_around_the_final_handler(): void
    {
        $pipeline = MiddlewarePipeline::wrap(
            [$this->appending('a'), $this->appending('b')],
            fn(ServerRequestInterface $request) => (new Response())->text(implode(',', $request->getAttribute('trail')))
        );

        $response = $pipeline->handle(Request::create()->withAttribute('trail', []));

        $this->assertSame('a,b', (string) $response->getBody());
        $this->assertSame(['b<', 'a<'], $response->getHeader('X-Trail'));
    }

    #[Test]
    public function test_middleware_can_return_early(): void
    {
        $stop = new class implements MiddlewareInterface {
            public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return (new Response())->withStatus(401);
            }
        };

        $pipeline = MiddlewarePipeline::wrap([$stop], fn() => throw new \LogicException('should not run'));

        $this->assertSame(401, $pipeline->handle(Request::create())->getStatusCode());
    }

    #[Test]
    public function test_without_middleware_the_final_handler_is_used(): void
    {
        $final = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return (new Response())->withStatus(204);
            }
        };

        $this->assertSame($final, MiddlewarePipeline::wrap([], $final));
    }

    /** Middleware that records its name on the way in and on the way out. */

    #[Test]
    public function test_middleware_given_by_class_name_is_built_by_the_resolver(): void
    {
        $resolver = new class implements \Dromos\Routing\ControllerResolverInterface {
            /** @var string[] */
            public array $resolved = [];

            public function resolve(string $class): object
            {
                $this->resolved[] = $class;
                return new $class();
            }
        };

        $response = MiddlewarePipeline::wrap([PipelineTagMiddleware::class], fn() => new Response(), $resolver)
            ->handle(Request::create());

        $this->assertSame([PipelineTagMiddleware::class], $resolver->resolved);
        $this->assertSame(['tagged'], $response->getHeader('X-Tag'));
    }

    #[Test]
    public function test_check_accepts_middleware_and_rejects_anything_else(): void
    {
        MiddlewarePipeline::check(new PipelineTagMiddleware());
        MiddlewarePipeline::check(PipelineTagMiddleware::class);

        foreach ([\stdClass::class, 'No\\Such\\Class', 42, new \stdClass()] as $notMiddleware) {
            try {
                MiddlewarePipeline::check($notMiddleware);
                $this->fail('accepted ' . get_debug_type($notMiddleware));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function appending(string $name): MiddlewareInterface
    {
        return new class ($name) implements MiddlewareInterface {
            public function __construct(private readonly string $name)
            {
            }

            public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $trail = [...$request->getAttribute('trail'), $this->name];
                return $handler->handle($request->withAttribute('trail', $trail))
                    ->withAddedHeader('X-Trail', $this->name . '<');
            }
        };
    }
}

final class PipelineTagMiddleware implements MiddlewareInterface
{
    public function handle(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('X-Tag', 'tagged');
    }
}
