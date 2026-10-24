<?php

declare(strict_types=1);

namespace Dromos\Http\Emitter;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\Stream;
use Dromos\Http\Message\StreamInterface;
use OpenSwoole\Http\Response as SwooleResponse;

/**
 * Sends a Dromos response through an OpenSwoole HTTP response, for apps
 * served by OpenSwoole's HTTP server. Create one per request, with the
 * response OpenSwoole passes to the request handler.
 *
 * Needs the openswoole extension (22 or later).
 */
final class SwooleEmitter implements EmitterInterface
{
    use ReadsBodyInChunks;

    public function __construct(private readonly SwooleResponse $target)
    {
    }

    public function emit(ResponseInterface $response): void
    {
        $this->target->status($response->getStatusCode(), $response->getReasonPhrase());

        foreach ($response->getHeaders() as $name => $values) {
            // header() replaces any earlier value for the same name, so all of a
            // header's values go in one call: OpenSwoole sends an array as one
            // header line per value, which Set-Cookie needs
            $values = array_values($values);
            $this->target->header($name, count($values) === 1 ? $values[0] : $values);
        }

        $this->emitBody($response->getBody());
    }

    /**
     * Send a body that fits in one chunk with end(), so OpenSwoole sets
     * Content-Length. Write a larger file or socket body in chunks, which
     * OpenSwoole sends with chunked transfer encoding.
     */
    private function emitBody(StreamInterface $body): void
    {
        $size = $body->getSize();
        if ($body instanceof Stream || ($size !== null && $size <= self::CHUNK_SIZE)) {
            $this->target->end((string) $body);
            return;
        }

        foreach ($this->chunksOf($body) as $chunk) {
            $this->target->write($chunk);
        }
        $this->target->end();
    }
}
