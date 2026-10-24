<?php

namespace Dromos\Http\Message;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * The default FileMoverInterface. A file PHP received as an upload is moved
 * with move_uploaded_file(), as PSR-7 recommends, so it is never copied. Any
 * other stream is copied in 8 KB chunks, so a large upload never sits in
 * memory whole. If a copy fails, the partly written target is removed.
 */
final class StreamFileMover implements FileMoverInterface
{
    private const CHUNK_SIZE = 8192;

    public function moveTo(StreamInterface $stream, string $targetPath): void
    {
        self::assertSafeTarget($targetPath);

        $source = $stream->getMetadata('uri');
        if (is_string($source) && is_uploaded_file($source)) {
            if (!@move_uploaded_file($source, $targetPath)) {
                throw new RuntimeException("Unable to move uploaded file to: $targetPath");
            }
            return;
        }

        $this->copy($stream, $targetPath);
    }

    private function copy(StreamInterface $stream, string $targetPath): void
    {
        $target = @fopen($targetPath, 'wb');
        if ($target === false) {
            throw new RuntimeException("Unable to open target path: $targetPath");
        }

        try {
            if ($stream->isSeekable()) {
                $stream->rewind();
            }
            while (!$stream->eof()) {
                $chunk = $stream->read(self::CHUNK_SIZE);
                if (@fwrite($target, $chunk) !== strlen($chunk)) {
                    throw new RuntimeException("Unable to write to target path: $targetPath");
                }
            }
        } catch (Throwable $e) {
            fclose($target);
            @unlink($targetPath);
            throw $e;
        }

        fclose($target);
    }

    /**
     * Refuse targets that could write outside the intended directory or
     * through a stream wrapper (php://, phar://, ...).
     */
    private static function assertSafeTarget(string $targetPath): void
    {
        if ($targetPath === '') {
            throw new InvalidArgumentException('Target path must be a non-empty string.');
        }
        if (str_contains($targetPath, "\0")) {
            throw new InvalidArgumentException('Target path must not contain null bytes.');
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $targetPath) === 1) {
            throw new InvalidArgumentException('Target path must not use a stream wrapper.');
        }
        if (preg_match('#(^|[/\\\\])\.\.($|[/\\\\])#', $targetPath) === 1) {
            throw new InvalidArgumentException('Target path must not contain a ".." segment.');
        }
    }
}
