<?php

namespace Dromos\Http;

use Dromos\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    protected string $data = '';

    public function __toString(): string
    {
        return $this->data;
    }
    public function close(): void
    { /* no-op */
    }
    public function detach()
    {
        $this->data = '';
        return null;
    }
    public function getSize(): ?int
    {
        return strlen($this->data);
    }
    public function tell(): int
    {
        return strlen($this->data);
    }
    public function eof(): bool
    {
        return true;
    }
    public function isSeekable(): bool
    {
        return false;
    }
    public function seek(int $offset, int $whence = SEEK_SET): void {}
    public function rewind(): void {}
    public function isWritable(): bool
    {
        return true;
    }
    public function write(string $string): int
    {
        $len = strlen($string);
        $this->data .= $string;
        return $len;
    }
    public function isReadable(): bool
    {
        return true;
    }
    public function read(int $length): string
    {
        return '';
    }
    public function getContents(): string
    {
        return $this->data;
    }
    public function getMetadata(?string $key = null)
    {
        return null;
    }
}
