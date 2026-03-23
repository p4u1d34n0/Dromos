<?php

declare(strict_types=1);

namespace Dromos\Http\Message;

use RuntimeException;

final class SyncFileMover implements FileMoverInterface
{
    public function moveTo(StreamInterface $stream, string $targetPath): void
    {
        $contents = (string) $stream;
        $bytes = file_put_contents($targetPath, $contents);

        if ($bytes === false) {
            throw new RuntimeException('Failed to write uploaded file to target location.');
        }
    }
}
