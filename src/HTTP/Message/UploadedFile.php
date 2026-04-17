<?php

declare(strict_types=1);

namespace Dromos\Http\Message;

use RuntimeException;
use InvalidArgumentException;

final class UploadedFile implements UploadedFileInterface
{
    /** @var int[] Valid UPLOAD_ERR_* constants (0–8). */
    private const VALID_ERRORS = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];

    private StreamInterface $stream;
    private ?int $size;
    private int $error;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private FileMoverInterface $fileMover;
    private bool $moved = false;

    public function __construct(
        StreamInterface $stream,
        ?int $size,
        int $error,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
        ?FileMoverInterface $fileMover = null
    ) {
        if (!in_array($error, self::VALID_ERRORS, true)) {
            throw new InvalidArgumentException(
                'Invalid upload error code: ' . $error . '. Must be a valid UPLOAD_ERR_* constant (0-8).'
            );
        }

        $this->stream = $stream;
        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
        $this->fileMover = $fileMover ?? new SyncFileMover();
    }

    public function getStream(): StreamInterface
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Cannot retrieve stream: upload error code ' . $this->error . '.'
            );
        }

        if ($this->moved) {
            throw new RuntimeException('Cannot retrieve stream after it has been moved.');
        }

        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot move uploaded file: upload error code ' . $this->error . '.');
        }

        if ($this->moved) {
            throw new RuntimeException('File has already been moved.');
        }

        if ($targetPath === '') {
            throw new InvalidArgumentException('Target path must be a non-empty string.');
        }

        $this->fileMover->moveTo($this->getStream(), $targetPath);
        $this->stream->detach();
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
