<?php

declare(strict_types=1);

namespace Dromos\Http\Emitter;

use Dromos\Http\Message\ResponseInterface;
use OpenSwoole\HTTP\Response as SwooleResponse;

/**
 * OpenSwoole HTTP Emitter for Dromos
 *
 * Bridges Dromos's PSR-7 ResponseInterface to OpenSwoole's HTTP Response,
 * allowing the framework to run on a long-lived Swoole HTTP server.
 */
final class SwooleEmitter implements EmitterInterface
{
    private const int CHUNK_SIZE = 8192;

    public function __construct(private readonly SwooleResponse $swooleResponse) {}

    public function emit(ResponseInterface $response): void
    {
        $this->swooleResponse->status($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $this->swooleResponse->header($name, $value, false);
            }
        }

        $this->emitBody($response);
    }

    /**
     * Emit the response body using chunked output for large payloads
     */
    private function emitBody(ResponseInterface $response): void
    {
        $body = $response->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $size = $body->getSize();

        if ($size === 0 || $size === null) {
            $this->swooleResponse->end();
            return;
        }

        if ($size <= self::CHUNK_SIZE) {
            $this->swooleResponse->end((string) $body);
            return;
        }

        while (!$body->eof()) {
            $this->swooleResponse->write($body->read(self::CHUNK_SIZE));
        }

        $this->swooleResponse->end();
    }
}
