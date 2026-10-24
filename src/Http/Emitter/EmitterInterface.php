<?php

namespace Dromos\Http\Emitter;

use Dromos\Http\Message\ResponseInterface;

/**
 * Emitter interface for sending PSR-7 responses
 */
interface EmitterInterface
{
    /**
     * Emit a response
     *
     * @param ResponseInterface $response The response to emit
     * @return void
     */
    public function emit(ResponseInterface $response): void;
}
