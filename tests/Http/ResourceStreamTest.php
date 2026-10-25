<?php

declare(strict_types=1);

namespace Dromos\Tests\Http;

use Dromos\Http\Message\ResourceStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** What the PSR-7 suite (tests/Psr7) doesn't cover. */
#[CoversClass(ResourceStream::class)]
final class ResourceStreamTest extends TestCase
{
    #[Test]
    public function test_from_string_holds_the_content_positioned_at_the_start(): void
    {
        $stream = ResourceStream::fromString('hello');

        $this->assertSame(0, $stream->tell());
        $this->assertSame('hel', $stream->read(3));
        $this->assertSame(5, $stream->getSize());
        $this->assertTrue($stream->isWritable());
    }

    #[Test]
    public function test_it_needs_an_open_stream_resource(): void
    {
        $closed = fopen('php://memory', 'r');
        fclose($closed);

        $this->expectException(\InvalidArgumentException::class);
        new ResourceStream($closed);
    }

    #[Test]
    public function test_it_closes_its_resource_when_destroyed(): void
    {
        $resource = fopen('php://memory', 'r+');
        $stream = new ResourceStream($resource);

        unset($stream);

        $this->assertFalse(is_resource($resource));
    }

    #[Test]
    public function test_a_detached_resource_stays_open_after_the_stream_is_destroyed(): void
    {
        $stream = ResourceStream::fromString('x');
        $resource = $stream->detach();

        unset($stream);

        $this->assertTrue(is_resource($resource));
        $this->assertSame('x', stream_get_contents($resource, -1, 0));
    }

    #[Test]
    public function test_a_read_only_file_is_readable_not_writable_and_reports_its_size(): void
    {
        $stream = new ResourceStream(fopen(__FILE__, 'rb'));

        $this->assertTrue($stream->isReadable());
        $this->assertFalse($stream->isWritable());
        $this->assertSame(filesize(__FILE__), $stream->getSize());
        $this->expectException(\RuntimeException::class);
        $stream->write('x');
    }

    #[Test]
    public function test_a_detached_stream_refuses_reads_and_reports_no_size(): void
    {
        $stream = ResourceStream::fromString('x');
        $stream->detach();

        $this->assertNull($stream->getSize());
        $this->assertTrue($stream->eof());
        $this->assertSame('', (string) $stream);
        $this->expectException(\RuntimeException::class);
        $stream->read(1);
    }
}
