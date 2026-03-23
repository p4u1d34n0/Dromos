<?php

declare(strict_types=1);

namespace Dromos\Http\Emitter;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\Stream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Namespace-scoped overrides for SAPI functions.
 *
 * Because SapiEmitter lives in Dromos\Http\Emitter, PHP resolves unqualified
 * function calls (header, headers_sent, ob_get_level, etc.) in that namespace
 * first. Defining them here lets us intercept every call without touching the
 * class under test.
 */

/** @var list<string> Captured header() calls */
$GLOBALS['__sapi_emitter_headers'] = [];

/** @var bool Value returned by our headers_sent() stub */
$GLOBALS['__sapi_emitter_headers_sent'] = false;

function header(string $header, bool $replace = true, int $responseCode = 0): void
{
    $GLOBALS['__sapi_emitter_headers'][] = $header;
}

function headers_sent(): bool
{
    return $GLOBALS['__sapi_emitter_headers_sent'];
}

function ob_get_level(): int
{
    return 0;
}

function ob_end_clean(): bool
{
    return true;
}

function ob_flush(): bool
{
    return true;
}

function flush(): void {}

#[CoversClass(SapiEmitter::class)]
final class SapiEmitterTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__sapi_emitter_headers'] = [];
        $GLOBALS['__sapi_emitter_headers_sent'] = false;
    }

    #[Test]
    public function test_it_emits_status_line(): void
    {
        $response = $this->createMockResponse(
            statusCode: 200,
            reasonPhrase: 'OK',
            headers: [],
            bodyContent: '',
        );

        $emitter = new SapiEmitter();
        $emitter->emit($response);

        $this->assertContains('HTTP/1.1 200 OK', $GLOBALS['__sapi_emitter_headers']);
    }

    #[Test]
    public function test_it_emits_headers(): void
    {
        $response = $this->createMockResponse(
            statusCode: 200,
            reasonPhrase: 'OK',
            headers: [
                'content-type' => ['application/json'],
                'x-custom' => ['value1', 'value2'],
            ],
            bodyContent: '',
        );

        $emitter = new SapiEmitter();
        $emitter->emit($response);

        $this->assertContains('Content-Type: application/json', $GLOBALS['__sapi_emitter_headers']);
        $this->assertContains('X-Custom: value1', $GLOBALS['__sapi_emitter_headers']);
        $this->assertContains('X-Custom: value2', $GLOBALS['__sapi_emitter_headers']);
    }

    #[Test]
    public function test_it_emits_body(): void
    {
        $response = $this->createMockResponse(
            statusCode: 200,
            reasonPhrase: 'OK',
            headers: [],
            bodyContent: 'Hello, Dromos!',
        );

        $emitter = new SapiEmitter();

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame('Hello, Dromos!', $output);
    }

    #[Test]
    public function test_it_throws_when_headers_already_sent(): void
    {
        $GLOBALS['__sapi_emitter_headers_sent'] = true;

        $response = $this->createMockResponse(
            statusCode: 200,
            reasonPhrase: 'OK',
            headers: [],
            bodyContent: '',
        );

        $emitter = new SapiEmitter();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Headers already sent');

        $emitter->emit($response);
    }

    #[Test]
    public function test_it_skips_body_when_empty(): void
    {
        $response = $this->createMockResponse(
            statusCode: 204,
            reasonPhrase: 'No Content',
            headers: [],
            bodyContent: '',
        );

        $emitter = new SapiEmitter();

        ob_start();
        $emitter->emit($response);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    #[Test]
    public function test_it_emits_status_without_reason_phrase(): void
    {
        $response = $this->createMockResponse(
            statusCode: 204,
            reasonPhrase: '',
            headers: [],
            bodyContent: '',
        );

        $emitter = new SapiEmitter();
        $emitter->emit($response);

        $this->assertContains('HTTP/1.1 204', $GLOBALS['__sapi_emitter_headers']);
    }

    private function createMockResponse(
        int $statusCode,
        string $reasonPhrase,
        array $headers,
        string $bodyContent,
        string $protocolVersion = '1.1',
    ): ResponseInterface {
        $body = new Stream();
        if ($bodyContent !== '') {
            $body->write($bodyContent);
            $body->rewind();
        }

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getReasonPhrase')->willReturn($reasonPhrase);
        $response->method('getProtocolVersion')->willReturn($protocolVersion);
        $response->method('getHeaders')->willReturn($headers);
        $response->method('getBody')->willReturn($body);

        return $response;
    }
}
