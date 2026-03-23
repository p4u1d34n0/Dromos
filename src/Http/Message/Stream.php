<?php

namespace Dromos\Http\Message;

use Dromos\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    protected string $data = '';
    protected int $position = 0;

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
        $this->position = 0;
        return null;
    }
    public function getSize(): ?int
    {
        return strlen($this->data);
    }
    public function tell(): int
    {
        return $this->position;
    }
    public function eof(): bool
    {
        return $this->position >= strlen($this->data);
    }
    public function isSeekable(): bool
    {
        return true;
    }
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $size = strlen($this->data);

        switch ($whence) {
            case SEEK_SET:
                $newPosition = $offset;
                break;
            case SEEK_CUR:
                $newPosition = $this->position + $offset;
                break;
            case SEEK_END:
                $newPosition = $size + $offset;
                break;
            default:
                throw new \InvalidArgumentException('Invalid whence value');
        }

        if ($newPosition < 0) {
            throw new \RuntimeException('Seek position cannot be negative');
        }

        $this->position = $newPosition;
    }
    public function rewind(): void
    {
        $this->position = 0;
    }
    public function isWritable(): bool
    {
        return true;
    }
    public function write(string $string): int
    {
        $len = strlen($string);

        $before = substr($this->data, 0, $this->position);
        $after  = substr($this->data, $this->position + $len);
        $this->data = $before . $string . $after;
        $this->position += $len;

        return $len;
    }
    public function isReadable(): bool
    {
        return true;
    }
    public function read(int $length): string
    {
        if ($this->eof()) {
            return '';
        }

        $result = substr($this->data, $this->position, $length);
        $this->position += strlen($result);

        return $result;
    }
    public function getContents(): string
    {
        $remaining = substr($this->data, $this->position);
        $this->position = strlen($this->data);

        return $remaining === false ? '' : $remaining;
    }
    public function getMetadata(?string $key = null)
    {
        return null;
    }
}
