<?php

declare(strict_types=1);

namespace Dromos\Http\Message;

use RuntimeException;

/**
 * Strategy for moving uploaded file contents to a target path.
 *
 * The implementation is responsible for rewinding/positioning the stream
 * before reading, and for writing the full stream contents to the target.
 *
 * WARNING: Implementors should validate the target path against directory
 * traversal attacks (e.g. "../" sequences) before writing. Never trust
 * user-supplied filenames as part of the target path without sanitisation.
 */
interface FileMoverInterface
{
    /**
     * @throws RuntimeException If the file cannot be written to the target path.
     */
    public function moveTo(StreamInterface $stream, string $targetPath): void;
}
