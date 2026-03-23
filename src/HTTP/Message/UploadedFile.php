<?php

namespace Dromos\Http\Message;

use RuntimeException;
use InvalidArgumentException;

class UploadedFile implements UploadedFileInterface
{
    private StreamInterface $stream;
    private ?int $size;
    private int $error;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private readonly FileMoverInterface $fileMover;
    private bool $moved = false;

    public function __construct(
        StreamInterface $stream,
        ?int $size,
        int $error,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
        ?FileMoverInterface $fileMover = null
    ) {
        $this->stream = $stream;
        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
        $this->fileMover = $fileMover ?? new SyncFileMover();
    }

    public function getStream(): StreamInterface
    {
        if ($this->moved) {
            throw new RuntimeException('Cannot retrieve stream after it has been moved.');
        }
        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        if ($this->moved) {
            throw new RuntimeException('File has already been moved.');
        }

        if (empty($targetPath)) {
            throw new InvalidArgumentException('Target path must be a non-empty string.');
        }

        $this->fileMover->moveTo($this->getStream(), $targetPath);
        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
