<?php

namespace Dromos\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Moves an uploaded file's contents to a target path for UploadedFile::moveTo().
 *
 * Implement this to store uploads somewhere other than the local disk, or to
 * move them under a server that doesn't receive uploads through PHP's SAPI
 * (OpenSwoole, RoadRunner).
 *
 * Implementations must not trust a client-supplied filename as part of the
 * target path. Build the path from values the application controls.
 */
interface FileMoverInterface
{
    /**
     * @throws InvalidArgumentException If the target path is not acceptable.
     * @throws RuntimeException         If the contents could not be written.
     */
    public function moveTo(StreamInterface $stream, string $targetPath): void;
}
