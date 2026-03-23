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
    public function __construct(private readonly SwooleResponse $swooleResponse) {}

    public function emit(ResponseInterface $response): void
    {
        $this->swooleResponse->status($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $this->swooleResponse->header($name, $value, false);
            }
        }

        $body = (string) $response->getBody();
        $this->swooleResponse->end($body);
    }
}
