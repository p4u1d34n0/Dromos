<?php

declare(strict_types=1);

namespace Dromos\Tests\Http;

use Dromos\Http\Message\Stream;
use Dromos\Http\Message\StreamFileMover;
use Dromos\Tests\Support\ChunkedReadStream;
use Dromos\Tests\Support\TemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * move_uploaded_file() only accepts files PHP received through an HTTP
 * upload, which can't happen under the CLI, so these tests cover the copy
 * path. The upload path is a single call guarded by is_uploaded_file().
 */
#[CoversClass(StreamFileMover::class)]
final class StreamFileMoverTest extends TestCase
{
    use TemporaryDirectory;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    #[Test]
    public function it_copies_a_large_stream_byte_for_byte_in_8_kb_chunks(): void
    {
        $content = random_bytes(50_000);
        $stream = new ChunkedReadStream($content); // throws if read whole
        $target = $this->temporaryDirectory() . '/upload.bin';

        (new StreamFileMover())->moveTo($stream, $target);

        $this->assertSame($content, file_get_contents($target));
        $this->assertCount(7, $stream->reads, '50,000 bytes is 7 chunks of 8 KB');
        $this->assertSame([8192], array_values(array_unique($stream->reads)));
    }

    #[Test]
    public function it_copies_from_the_start_of_a_partly_read_stream(): void
    {
        $stream = new Stream('the whole file');
        $stream->read(4);
        $target = $this->temporaryDirectory() . '/upload.txt';

        (new StreamFileMover())->moveTo($stream, $target);

        $this->assertSame('the whole file', file_get_contents($target));
    }

    #[Test]
    public function it_copies_a_non_seekable_stream_without_rewinding_it(): void
    {
        $stream = new ChunkedReadStream('abc', seekable: false);
        $target = $this->temporaryDirectory() . '/upload.txt';

        (new StreamFileMover())->moveTo($stream, $target);

        $this->assertSame('abc', file_get_contents($target));
        $this->assertSame(0, $stream->rewinds);
    }

    #[Test]
    public function it_copies_an_empty_stream_to_an_empty_file(): void
    {
        $target = $this->temporaryDirectory() . '/empty.txt';

        (new StreamFileMover())->moveTo(new Stream(''), $target);

        $this->assertFileExists($target);
        $this->assertSame('', file_get_contents($target));
    }

    #[Test]
    public function it_removes_the_partly_written_target_when_the_stream_fails(): void
    {
        $stream = new ChunkedReadStream(random_bytes(30_000), failAfterReads: 2);
        $target = $this->temporaryDirectory() . '/upload.bin';

        try {
            (new StreamFileMover())->moveTo($stream, $target);
            $this->fail('The stream failure should propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('The stream failed mid-read.', $e->getMessage());
        }

        $this->assertCount(2, $stream->reads, 'two chunks were written before the failure');
        $this->assertFileDoesNotExist($target);
    }

    #[Test]
    public function it_throws_when_the_target_cannot_be_opened(): void
    {
        $target = $this->temporaryDirectory() . '/missing-directory/upload.txt';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to open target path');

        (new StreamFileMover())->moveTo(new Stream('data'), $target);
    }

    /** @return array<string, array{string}> */
    public static function unsafeTargets(): array
    {
        return [
            'empty'                      => [''],
            'null byte'                  => ["upload.txt\0.php"],
            'parent directory'           => ['../upload.txt'],
            'parent directory mid-path'  => ['uploads/../../etc/upload.txt'],
            'parent directory at end'    => ['uploads/..'],
            'Windows parent directory'   => ['uploads\\..\\upload.txt'],
            'php:// wrapper'             => ['php://memory'],
            'phar:// wrapper'            => ['phar://archive.phar/upload.txt'],
            'upper-case wrapper'         => ['PHP://filter/resource=upload.txt'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeTargets')]
    public function it_refuses_an_unsafe_target_without_reading_the_stream(string $target): void
    {
        $stream = new ChunkedReadStream('data');

        try {
            (new StreamFileMover())->moveTo($stream, $target);
            $this->fail('The target should be refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $stream->reads);
        }
    }

    #[Test]
    public function it_allows_dots_that_are_not_a_parent_directory_segment(): void
    {
        $directory = $this->temporaryDirectory();

        foreach (['my..file.txt', '..hidden', 'file..'] as $name) {
            (new StreamFileMover())->moveTo(new Stream($name), "$directory/$name");
            $this->assertSame($name, file_get_contents("$directory/$name"));
        }
    }
}
