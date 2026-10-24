<?php

declare(strict_types=1);

namespace Dromos\Tests\Http;

use Dromos\Http\Emitter\SwooleEmitter;
use Dromos\Http\Message\Stream;
use Dromos\Http\Response;
use Dromos\Tests\Support\ResourceStream;
use OpenSwoole\Http\Response as SwooleResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/openswoole-response.php';

#[CoversClass(SwooleEmitter::class)]
final class SwooleEmitterTest extends TestCase
{
    private SwooleResponse $target;

    protected function setUp(): void
    {
        if (extension_loaded('openswoole')) {
            $this->markTestSkipped('these tests inspect a stand-in for the extension\'s response');
        }
        $this->target = new SwooleResponse();
    }

    private function emit(Response $response): void
    {
        (new SwooleEmitter($this->target))->emit($response);
    }

    #[Test]
    public function test_it_sends_the_status_code_and_reason_phrase(): void
    {
        $this->emit(new Response(404));
        $this->assertSame([404, 'Not Found'], [$this->target->status, $this->target->reason]);

        $this->target = new SwooleResponse();
        $this->emit((new Response())->withStatus(299, 'Custom Reason'));
        $this->assertSame([299, 'Custom Reason'], [$this->target->status, $this->target->reason]);
    }

    #[Test]
    public function test_it_sends_every_value_of_every_header(): void
    {
        $this->emit((new Response())
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Tags', ['first' => 'a', 'second' => 'b'])
            ->withHeader('X-Single', ['only' => 'x'])
            ->withHeader('Set-Cookie', 'a=1; HttpOnly')
            ->withAddedHeader('Set-Cookie', 'b=2; Secure')
            ->withAddedHeader('Set-Cookie', 'c=3'));

        // One call per header name: the real header() would replace earlier values
        $this->assertSame([
            'Content-Type' => 'application/json',
            'X-Tags' => ['a', 'b'],
            'X-Single' => 'x',
            'Set-Cookie' => ['a=1; HttpOnly', 'b=2; Secure', 'c=3'],
        ], $this->target->headers);
    }

    #[Test]
    public function test_it_sends_an_in_memory_body_with_one_end_call(): void
    {
        $body = str_repeat('0123456789', 3000); // larger than one 8 KB chunk

        $this->emit((new Response())->withBody(new Stream($body)));

        $this->assertSame([], $this->target->writes);
        $this->assertSame([$body], $this->target->ends);
    }

    #[Test]
    public function test_it_sends_an_empty_body_with_one_end_call(): void
    {
        $this->emit(new Response(204));

        $this->assertSame([], $this->target->writes);
        $this->assertSame([''], $this->target->ends);
    }

    #[Test]
    public function test_it_sends_a_small_stream_with_one_end_call_from_the_start(): void
    {
        $stream = new ResourceStream('{"id":7}');
        $stream->read(3);

        $this->emit((new Response())->withBody($stream));

        $this->assertSame([], $this->target->writes);
        $this->assertSame(['{"id":7}'], $this->target->ends);
    }

    #[Test]
    public function test_it_writes_a_large_stream_in_ordered_chunks_from_the_start(): void
    {
        $content = '';
        for ($i = 0; strlen($content) < 20_000; $i++) {
            $content .= "line $i\n"; // every chunk differs, so order matters
        }
        $stream = new ResourceStream($content);
        $stream->read(100); // a body that was already partly read

        $this->emit((new Response())->withBody($stream));

        $this->assertSame((int) ceil(strlen($content) / 8192), count($this->target->writes));
        foreach ($this->target->writes as $chunk) {
            $this->assertLessThanOrEqual(8192, strlen($chunk));
        }
        $this->assertSame($content, implode('', $this->target->writes));
        $this->assertSame([null], $this->target->ends, 'end() is called once, after the last chunk');
    }
}
