<?php

declare(strict_types=1);

/**
 * Stub for OpenSwoole\HTTP\Response used in tests.
 *
 * OpenSwoole is not a dev dependency, so we provide a minimal stand-in
 * that records every call the SwooleEmitter makes, allowing assertions
 * without requiring the extension.
 */

namespace OpenSwoole\HTTP;

class Response
{
    public int $statusCode = 200;

    /** @var list<array{string, string, bool}> */
    public array $headers = [];

    public string $body = '';
    public bool $ended = false;

    /** @var list<string> Chunks passed to write() */
    public array $chunks = [];

    public function status(int $code): void
    {
        $this->statusCode = $code;
    }

    public function header(string $name, string $value, bool $ucWords = true): void
    {
        $this->headers[] = [$name, $value, $ucWords];
    }

    public function write(string $data): void
    {
        $this->chunks[] = $data;
        $this->body .= $data;
    }

    public function end(string $data = ''): void
    {
        $this->body .= $data;
        $this->ended = true;
    }
}

/**
 * Test-side alias that re-exports the stub with a friendlier name.
 */

namespace Dromos\Tests\HTTP\Emitter;

use OpenSwoole\HTTP\Response;

class StubSwooleResponse extends Response {}
