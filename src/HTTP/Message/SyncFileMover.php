<?php

declare(strict_types=1);

namespace Dromos\Http\Message;

use InvalidArgumentException;
use RuntimeException;

final class SyncFileMover implements FileMoverInterface
{
    public function moveTo(StreamInterface $stream, string $targetPath): void
    {
        $this->validateTargetPath($targetPath);

        $contents = (string) $stream;
        $bytes = @file_put_contents($targetPath, $contents);

        if ($bytes === false) {
            throw new RuntimeException('Failed to write uploaded file to target location.');
        }
    }

    private function validateTargetPath(string $targetPath): void
    {
        if (str_contains($targetPath, "\0")) {
            throw new InvalidArgumentException('Target path must not contain null bytes.');
        }

        if (preg_match('#(^|[\\\\/])\\.\\.($|[\\\\/])#', $targetPath)) {
            throw new InvalidArgumentException('Target path must not contain directory traversal sequences.');
        }

        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*://#', $targetPath)) {
            throw new InvalidArgumentException('Target path must not use a stream wrapper.');
        }
    }
}
