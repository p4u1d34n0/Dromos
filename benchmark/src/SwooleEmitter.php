<?php

namespace Benchmark;

use Dromos\Http\Emitter\EmitterInterface;
use Dromos\Http\Message\ResponseInterface;
use OpenSwoole\HTTP\Response as SwooleResponse;

/**
 * OpenSwoole emitter for Dromos
 *
 * Bridges Dromos's PSR-7 ResponseInterface to OpenSwoole's HTTP Response,
 * allowing the framework to run on a long-lived Swoole HTTP server.
 */
class SwooleEmitter implements EmitterInterface
{
    public function __construct(private SwooleResponse $swooleResponse) {}

    public function emit(ResponseInterface $response): void
    {
        // Status
        $this->swooleResponse->status($response->getStatusCode());

        // Headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $this->swooleResponse->header($name, $value, false);
            }
        }

        // Body
        $body = (string) $response->getBody();
        $this->swooleResponse->end($body);
    }
}
