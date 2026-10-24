<?php

declare(strict_types=1);

namespace Dromos\Http\Emitter;

use Dromos\Http\Message\StreamInterface;
use Generator;

/**
 * Reads a response body in 8 KB chunks from the start, so emitters can send
 * large file and socket bodies without holding them in memory whole.
 */
trait ReadsBodyInChunks
{
    private const CHUNK_SIZE = 8192;

    /**
     * @return Generator<int, string>
     */
    private function chunksOf(StreamInterface $body): Generator
    {
        if ($body->isSeekable()) {
            $body->rewind();
        }
        while (!$body->eof()) {
            $chunk = $body->read(self::CHUNK_SIZE);
            if ($chunk !== '') {
                yield $chunk;
            }
        }
    }
}
