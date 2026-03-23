<?php

declare(strict_types=1);

namespace Dromos\Tests\HTTP\Emitter;

use Dromos\Http\Emitter\SwooleEmitter;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SwooleEmitter::class)]
final class SwooleEmitterTest extends TestCase
{
    #[Test]
    public function test_it_sets_status_code(): void
    {
        $swooleResponse = new StubSwooleResponse();
        $response = $this->createMockResponse(201, [], '');

        $emitter = new SwooleEmitter($swooleResponse);
        $emitter->emit($response);

        $this->assertSame(201, $swooleResponse->statusCode);
    }

    #[Test]
    public function test_it_sets_headers(): void
    {
        $swooleResponse = new StubSwooleResponse();
        $response = $this->createMockResponse(200, [
            'Content-Type' => ['application/json'],
            'X-Request-Id' => ['abc-123'],
        ], '');

        $emitter = new SwooleEmitter($swooleResponse);
        $emitter->emit($response);

        $this->assertSame([
            ['Content-Type', 'application/json', false],
            ['X-Request-Id', 'abc-123', false],
        ], $swooleResponse->headers);
    }

    #[Test]
    public function test_it_sets_multi_value_headers(): void
    {
        $swooleResponse = new StubSwooleResponse();
        $response = $this->createMockResponse(200, [
            'Set-Cookie' => ['a=1', 'b=2'],
        ], '');

        $emitter = new SwooleEmitter($swooleResponse);
        $emitter->emit($response);

        $this->assertSame([
            ['Set-Cookie', 'a=1', false],
            ['Set-Cookie', 'b=2', false],
        ], $swooleResponse->headers);
    }

    #[Test]
    public function test_it_sends_body(): void
    {
        $swooleResponse = new StubSwooleResponse();
        $response = $this->createMockResponse(200, [], '{"ok":true}');

        $emitter = new SwooleEmitter($swooleResponse);
        $emitter->emit($response);

        $this->assertSame('{"ok":true}', $swooleResponse->body);
        $this->assertTrue($swooleResponse->ended);
    }

    #[Test]
    public function test_it_sends_empty_body(): void
    {
        $swooleResponse = new StubSwooleResponse();
        $response = $this->createMockResponse(204, [], '');

        $emitter = new SwooleEmitter($swooleResponse);
        $emitter->emit($response);

        $this->assertSame('', $swooleResponse->body);
        $this->assertTrue($swooleResponse->ended);
    }

    private function createMockResponse(int $statusCode, array $headers, string $bodyContent): ResponseInterface
    {
        $body = new Stream();
        if ($bodyContent !== '') {
            $body->write($bodyContent);
            $body->rewind();
        }

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getHeaders')->willReturn($headers);
        $response->method('getBody')->willReturn($body);

        return $response;
    }
}
