<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Response;
use Dromos\Router;
use Dromos\Routing\ControllerResolverInterface;
use Dromos\Tests\Support\RouterTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Router::class)]
final class RouterControllerTest extends RouterTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CountingController::$constructed = 0;
        OtherCountingController::$constructed = 0;
    }

    #[Test]
    public function test_registering_a_route_does_not_create_its_controller(): void
    {
        Router::Get('/a', [CountingController::class, 'show']);
        Router::Resource('/things/{id}', CountingController::class)->onlyMethods(['GET', 'PUT']);

        $this->assertSame(0, CountingController::$constructed);
    }

    #[Test]
    public function test_only_the_dispatched_routes_controller_is_created(): void
    {
        Router::Get('/a', [CountingController::class, 'show']);
        Router::Get('/b', [OtherCountingController::class, 'show']);

        $this->assertSame(200, $this->response('GET', '/b')->getStatusCode());

        $this->assertSame(0, CountingController::$constructed);
        $this->assertSame(1, OtherCountingController::$constructed);
    }

    #[Test]
    public function test_one_instance_per_class_is_shared_across_requests_and_routes(): void
    {
        Router::Get('/a', [CountingController::class, 'show']);
        Router::Get('/a/{id}', [CountingController::class, 'item']);
        Router::Get('/legacy', ['\\' . CountingController::class, 'show']);

        $this->assertSame(['instance' => 1, 'calls' => 1], $this->body('GET', '/a'));
        $this->assertSame(['instance' => 1, 'calls' => 2, 'id' => '9'], $this->body('GET', '/a/9'));
        $this->assertSame(['instance' => 1, 'calls' => 3], $this->body('GET', '/legacy'));
        $this->assertSame(1, CountingController::$constructed);
    }

    #[Test]
    public function test_reset_discards_controller_instances(): void
    {
        Router::Get('/a', [CountingController::class, 'show']);
        $this->body('GET', '/a');

        Router::reset();
        Router::Get('/a', [CountingController::class, 'show']);

        $this->assertSame(['instance' => 2, 'calls' => 1], $this->body('GET', '/a'));
    }

    #[Test]
    public function test_an_unknown_controller_class_is_rejected_at_registration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route target class Dromos\\Tests\\NoSuchController does not exist.');

        Router::Get('/a', [NoSuchController::class, 'show']);
    }

    #[Test]
    public function test_a_custom_controller_resolver_supplies_controllers(): void
    {
        $resolver = new class implements ControllerResolverInterface {
            /** @var string[] */
            public array $resolved = [];

            public function resolve(string $class): object
            {
                $this->resolved[] = $class;
                return new $class();
            }
        };
        Router::setControllerResolver($resolver);
        Router::Get('/a', [CountingController::class, 'show']);

        $this->assertSame(['instance' => 1, 'calls' => 1], $this->body('GET', '/a'));
        $this->assertSame(['instance' => 2, 'calls' => 1], $this->body('GET', '/a'));
        $this->assertSame([CountingController::class, CountingController::class], $resolver->resolved);

        Router::reset();
        Router::Get('/a', [CountingController::class, 'show']);
        $this->body('GET', '/a');
        $this->assertCount(2, $resolver->resolved, 'reset() restores the default resolver');
    }

    #[Test]
    public function test_a_missing_controller_method_returns_500_when_dispatched(): void
    {
        Router::Get('/a', [CountingController::class, 'nope']);

        $this->assertSame(500, $this->response('GET', '/a')->getStatusCode());
    }
}

class CountingController
{
    public static int $constructed = 0;

    private int $instance;
    private int $calls = 0;

    public function __construct()
    {
        $this->instance = ++static::$constructed;
    }

    public function show(Response $response): ResponseInterface
    {
        return $response->json(['instance' => $this->instance, 'calls' => ++$this->calls]);
    }

    public function item(Response $response, string $id): ResponseInterface
    {
        return $response->json(['instance' => $this->instance, 'calls' => ++$this->calls, 'id' => $id]);
    }

    public function get(Response $response): ResponseInterface
    {
        return $this->show($response);
    }

    public function put(Response $response): ResponseInterface
    {
        return $this->show($response);
    }
}

final class OtherCountingController extends CountingController
{
    public static int $constructed = 0;
}
