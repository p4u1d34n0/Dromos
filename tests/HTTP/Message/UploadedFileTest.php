<?php

declare(strict_types=1);

namespace Dromos\Tests\HTTP\Message;

use Dromos\Http\Message\FileMoverInterface;
use Dromos\Http\Message\Stream;
use Dromos\Http\Message\StreamInterface;
use Dromos\Http\Message\UploadedFile;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(UploadedFile::class)]
final class UploadedFileTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/dromos_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            array_map('unlink', $files);
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function test_it_moves_file_to_target_path(): void
    {
        $content = 'Hello, Dromos!';
        $stream = new Stream();
        $stream->write($content);

        $file = new UploadedFile($stream, strlen($content), UPLOAD_ERR_OK);
        $targetPath = $this->tempDir . '/moved_file.txt';

        $file->moveTo($targetPath);

        $this->assertFileExists($targetPath);
        $this->assertSame($content, file_get_contents($targetPath));
    }

    #[Test]
    public function test_it_throws_when_already_moved(): void
    {
        $stream = new Stream();
        $stream->write('data');

        $file = new UploadedFile($stream, 4, UPLOAD_ERR_OK);
        $file->moveTo($this->tempDir . '/first.txt');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File has already been moved.');

        $file->moveTo($this->tempDir . '/second.txt');
    }

    #[Test]
    public function test_it_throws_on_empty_target_path(): void
    {
        $stream = new Stream();
        $stream->write('data');

        $file = new UploadedFile($stream, 4, UPLOAD_ERR_OK);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Target path must be a non-empty string.');

        $file->moveTo('');
    }

    #[Test]
    public function test_it_delegates_to_custom_file_mover(): void
    {
        $stream = new Stream();
        $stream->write('data');

        $mover = $this->createMock(FileMoverInterface::class);
        $mover->expects($this->once())
            ->method('moveTo')
            ->with(
                $this->isInstanceOf(StreamInterface::class),
                '/some/target/path',
            );

        $file = new UploadedFile(
            stream: $stream,
            size: 4,
            error: UPLOAD_ERR_OK,
            fileMover: $mover,
        );

        $file->moveTo('/some/target/path');
    }

    #[Test]
    public function test_it_marks_stream_unavailable_after_move(): void
    {
        $stream = new Stream();
        $stream->write('data');

        $file = new UploadedFile($stream, 4, UPLOAD_ERR_OK);
        $file->moveTo($this->tempDir . '/moved.txt');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot retrieve stream after it has been moved.');

        $file->getStream();
    }
}
