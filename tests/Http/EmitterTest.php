<?php

declare(strict_types=1);

namespace Dromos\Tests\Http;

use Dromos\Http\Emitter\Emitter;
use Dromos\Http\Response;
use Dromos\Tests\Support\ResourceStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Emitter::class)]
final class EmitterTest extends TestCase
{
    /** Emit inside an output buffer and return what was written to it. */
    private function emitted(Response $response): string
    {
        ob_start();
        $level = ob_get_level();
        try {
            (new Emitter())->emit($response);
            $this->assertSame($level, ob_get_level(), 'the caller\'s output buffer is left open');
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    #[Test]
    public function test_it_writes_the_whole_body_into_the_callers_output_buffer(): void
    {
        $body = str_repeat('0123456789', 3000); // larger than one 8 KB chunk

        $this->assertSame($body, $this->emitted((new Response())->text($body)));
    }

    #[Test]
    public function test_it_writes_the_body_even_after_it_was_read(): void
    {
        $response = (new Response())->text('hello');
        $response->getBody()->getContents();

        $this->assertSame('hello', $this->emitted($response));
    }

    #[Test]
    public function test_it_writes_nothing_for_an_empty_body(): void
    {
        $this->assertSame('', $this->emitted((new Response())->noContent()));
    }

    #[Test]
    public function test_it_discards_output_buffers_opened_after_it_was_created(): void
    {
        ob_start(); // the caller's buffer
        $level = ob_get_level();
        try {
            $emitter = new Emitter();
            ob_start();
            echo 'stray debug output';
            $emitter->emit((new Response())->text('body'));

            $this->assertSame($level, ob_get_level(), 'the later buffer is closed');
            $this->assertSame('body', (string) ob_get_contents());
        } finally {
            while (ob_get_level() >= $level) {
                ob_end_clean();
            }
        }
    }

    #[Test]
    public function test_it_writes_a_non_memory_stream_in_full_from_the_start(): void
    {
        $content = str_repeat('abcdefghij', 2000);
        $stream = new ResourceStream($content);
        $stream->read(100); // a body that was already partly read

        $this->assertSame($content, $this->emitted((new Response())->withBody($stream)));
    }
}
