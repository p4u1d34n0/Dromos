<?php

declare(strict_types=1);

namespace Dromos\Tests\Support;

use Dromos\Http\Message\StreamInterface;

/** A php://memory stream, standing in for file and socket streams. */
final class ResourceStream implements StreamInterface
{
    /** @var resource */
    private $handle;

    public function __construct(string $content)
    {
        $this->handle = fopen('php://memory', 'r+b');
        fwrite($this->handle, $content);
        rewind($this->handle);
    }

    public function __toString(): string
    {
        rewind($this->handle);
        return (string) stream_get_contents($this->handle);
    }
    public function close(): void
    {
        fclose($this->handle);
    }
    public function detach()
    {
        return $this->handle;
    }
    public function getSize(): ?int
    {
        return fstat($this->handle)['size'] ?? null;
    }
    public function tell(): int
    {
        return (int) ftell($this->handle);
    }
    public function eof(): bool
    {
        return feof($this->handle);
    }
    public function isSeekable(): bool
    {
        return true;
    }
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        fseek($this->handle, $offset, $whence);
    }
    public function rewind(): void
    {
        rewind($this->handle);
    }
    public function isWritable(): bool
    {
        return true;
    }
    public function write(string $string): int
    {
        return (int) fwrite($this->handle, $string);
    }
    public function isReadable(): bool
    {
        return true;
    }
    public function read(int $length): string
    {
        return (string) fread($this->handle, $length);
    }
    public function getContents(): string
    {
        return (string) stream_get_contents($this->handle);
    }
    public function getMetadata(?string $key = null)
    {
        return null;
    }
}
