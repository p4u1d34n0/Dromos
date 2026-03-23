<?php

declare(strict_types=1);

namespace Dromos\Tests\HTTP\Message;

use Dromos\Http\Message\Stream;
use Dromos\Http\Message\SyncFileMover;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SyncFileMover::class)]
final class SyncFileMoverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/dromos_sync_mover_' . uniqid();
        mkdir($this->tempDir, 0700, true);
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
    public function test_it_writes_stream_contents_to_file(): void
    {
        $content = 'Stream content for file mover test.';
        $stream = new Stream();
        $stream->write($content);

        $mover = new SyncFileMover();
        $targetPath = $this->tempDir . '/output.txt';

        $mover->moveTo($stream, $targetPath);

        $this->assertFileExists($targetPath);
        $this->assertSame($content, file_get_contents($targetPath));
    }

    #[Test]
    public function test_it_throws_on_unwritable_path(): void
    {
        $stream = new Stream();
        $stream->write('data');

        $mover = new SyncFileMover();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to write uploaded file to target location.');

        $mover->moveTo($stream, '/nonexistent/directory/file.txt');
    }
}
