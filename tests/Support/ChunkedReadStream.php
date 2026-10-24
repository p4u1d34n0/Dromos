<?php

declare(strict_types=1);

namespace Dromos\Tests\Support;

use Dromos\Http\Message\StreamInterface;
use LogicException;
use RuntimeException;

/**
 * A stream that can only be read with read(), and records each read's
 * length, so a test can prove its contents were never read whole. It can
 * also fail after a number of reads, like a dropped connection.
 */
final class ChunkedReadStream implements StreamInterface
{
    /** @var list<int> The length asked for by each read() call. */
    public array $reads = [];

    public int $rewinds = 0;

    private int $position = 0;

    public function __construct(
        private readonly string $content,
        private readonly bool $seekable = true,
        private readonly ?int $failAfterReads = null,
    ) {
    }

    public function __toString(): string
    {
        throw new LogicException('The stream was read whole with __toString().');
    }
    public function getContents(): string
    {
        throw new LogicException('The stream was read whole with getContents().');
    }
    public function read(int $length): string
    {
        if ($this->failAfterReads !== null && count($this->reads) >= $this->failAfterReads) {
            throw new RuntimeException('The stream failed mid-read.');
        }
        $this->reads[] = $length;
        $chunk = substr($this->content, $this->position, $length);
        $this->position += strlen($chunk);
        return $chunk;
    }
    public function eof(): bool
    {
        return $this->position >= strlen($this->content);
    }
    public function isSeekable(): bool
    {
        return $this->seekable;
    }
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->seekable) {
            throw new RuntimeException('The stream is not seekable.');
        }
        $this->position = $offset;
    }
    public function rewind(): void
    {
        $this->seek(0);
        $this->rewinds++;
    }
    public function tell(): int
    {
        return $this->position;
    }
    public function getSize(): ?int
    {
        return strlen($this->content);
    }
    public function isReadable(): bool
    {
        return true;
    }
    public function isWritable(): bool
    {
        return false;
    }
    public function write(string $string): int
    {
        throw new RuntimeException('The stream is not writable.');
    }
    public function close(): void
    {
    }
    public function detach()
    {
        return null;
    }
    public function getMetadata(?string $key = null)
    {
        return null;
    }
}
