<?php

declare(strict_types=1);

namespace Dromos\Http\Message;

use InvalidArgumentException;
use RuntimeException;

/**
 * A stream over a PHP stream resource: a file, socket, php://temp and so on.
 * Unlike Stream, its content isn't held in memory, so it suits large bodies.
 *
 * Once closed or detached it is unusable: reads, writes and seeks throw, as
 * PSR-7 requires.
 */
final class ResourceStream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    private ?bool $seekable = null;
    private ?bool $readable = null;
    private ?bool $writable = null;

    /**
     * @param resource $resource
     * @throws InvalidArgumentException If $resource isn't an open stream resource
     */
    public function __construct($resource)
    {
        if (!is_resource($resource) || get_resource_type($resource) !== 'stream') {
            throw new InvalidArgumentException('ResourceStream needs an open stream resource, got ' . get_debug_type($resource) . '.');
        }
        $this->resource = $resource;
    }

    /**
     * A php://temp stream holding $content, positioned at the start. It's
     * kept in memory up to 2 MB and in a temporary file beyond that.
     */
    public static function fromString(string $content): self
    {
        $resource = fopen('php://temp', 'r+b');
        if ($resource === false) {
            throw new RuntimeException('Could not open php://temp.');
        }
        fwrite($resource, $content);
        rewind($resource);
        return new self($resource);
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * All of the content from the start, or '' if it can't be read, as
     * PSR-7 says this method must not throw.
     */
    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->seek(0);
            }
            return $this->getContents();
        } catch (RuntimeException) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            $resource = $this->detach();
            if (is_resource($resource)) {
                fclose($resource);
            }
        }
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        $this->seekable = $this->readable = $this->writable = false;
        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }
        $uri = $this->getMetadata('uri');
        if (is_string($uri)) {
            clearstatcache(true, $uri); // a file may have grown since the last stat
        }
        $stats = fstat($this->resource);
        return $stats === false ? null : $stats['size'];
    }

    public function tell(): int
    {
        $position = ftell($this->open());
        return $position === false ? throw new RuntimeException('Could not tell the stream position.') : $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->seekable ??= (bool) $this->getMetadata('seekable');
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('The stream is not seekable.');
        }
        if (fseek($this->open(), $offset, $whence) === -1) {
            throw new RuntimeException("Could not seek to $offset (whence $whence).");
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable ??= $this->modeHasAny('waxc+');
    }

    public function write(string $string): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('The stream is not writable.');
        }
        $written = fwrite($this->open(), $string);
        return $written === false ? throw new RuntimeException('Could not write to the stream.') : $written;
    }

    public function isReadable(): bool
    {
        return $this->readable ??= $this->modeHasAny('r+');
    }

    public function read(int $length): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('The stream is not readable.');
        }
        if ($length < 0) {
            throw new RuntimeException('The length to read must not be negative.');
        }
        if ($length === 0) {
            return '';
        }
        $data = fread($this->open(), $length);
        return $data === false ? throw new RuntimeException('Could not read from the stream.') : $data;
    }

    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('The stream is not readable.');
        }
        $contents = stream_get_contents($this->open());
        return $contents === false ? throw new RuntimeException('Could not read the stream.') : $contents;
    }

    public function getMetadata(?string $key = null)
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }
        $metadata = stream_get_meta_data($this->resource);
        return $key === null ? $metadata : ($metadata[$key] ?? null);
    }

    /** @return resource */
    private function open()
    {
        return $this->resource ?? throw new RuntimeException('The stream is detached or closed.');
    }

    private function modeHasAny(string $characters): bool
    {
        $mode = $this->getMetadata('mode');
        return is_string($mode) && strpbrk($mode, $characters) !== false;
    }
}
