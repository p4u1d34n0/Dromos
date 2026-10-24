<?php

namespace Dromos\Http\Message;

use RuntimeException;
use InvalidArgumentException;

class UploadedFile implements UploadedFileInterface
{
    /** The UPLOAD_ERR_* codes PHP defines (there is no 5). */
    private const ERROR_CODES = [
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

    /**
     * @param int                     $error     One of the UPLOAD_ERR_* constants
     * @param FileMoverInterface|null $fileMover How moveTo() writes the file; StreamFileMover by default
     */
    public function __construct(
        StreamInterface $stream,
        ?int $size,
        int $error,
        ?string $clientFilename = null,
        ?string $clientMediaType = null,
        ?FileMoverInterface $fileMover = null
    ) {
        if (!in_array($error, self::ERROR_CODES, true)) {
            throw new InvalidArgumentException("Invalid upload error code: $error. Use one of the UPLOAD_ERR_* constants.");
        }

        $this->stream = $stream;
        $this->size = $size;
        $this->error = $error;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
        $this->fileMover = $fileMover ?? new StreamFileMover();
    }

    public function getStream(): StreamInterface
    {
        $this->assertUploaded('retrieve the stream');
        return $this->stream;
    }

    public function moveTo(string $targetPath): void
    {
        $this->assertUploaded('move the file');

        if ($targetPath === '') {
            throw new InvalidArgumentException('Target path must be a non-empty string.');
        }

        $this->fileMover->moveTo($this->stream, $targetPath);
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

    /**
     * The upload succeeded and hasn't been moved yet; otherwise there is no
     * file to read or move.
     */
    private function assertUploaded(string $action): void
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Cannot $action: the upload failed with error code {$this->error}.");
        }
        if ($this->moved) {
            throw new RuntimeException("Cannot $action: the file has already been moved.");
        }
    }
}
