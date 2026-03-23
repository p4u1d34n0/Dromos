<?php

declare(strict_types=1);

namespace Dromos\Http\Message;

use RuntimeException;

final class SyncFileMover implements FileMoverInterface
{
    public function moveTo(StreamInterface $stream, string $targetPath): void
    {
        $stream->rewind();

        set_error_handler(static fn (): bool => true);
        try {
            $dest = fopen($targetPath, 'wb');
        } finally {
            restore_error_handler();
        }

        if ($dest === false) {
            throw new RuntimeException("Unable to open target path: {$targetPath}");
        }

        try {
            while (!$stream->eof()) {
                fwrite($dest, $stream->read(8192));
            }
        } finally {
            fclose($dest);
        }
    }
}
