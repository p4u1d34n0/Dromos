<?php

namespace Dromos\Http;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Traits\MessageTrait;

use Dromos\Http\Message\Stream;

/**
 * Class Response
 *
 * @package Dromos\Http
 */

class Response implements ResponseInterface
{
    use MessageTrait;

    protected int $statusCode = 200;
    protected string $reasonPhrase = 'OK';

    public function __construct(int $code = 200, string $reason = '')
    {
        $this->statusCode   = $code;
        $this->reasonPhrase = $reason ?: $this->reasonPhrase;
        $this->body         = new Stream();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    public function withStatus(int $code, string $reason = ''): static
    {
        $c = clone $this;
        $c->statusCode = $code;
        if ($reason) $c->reasonPhrase = $reason;
        return $c;
    }
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }
}
