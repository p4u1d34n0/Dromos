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

    public function __clone()
    {
        $original = $this->body;
        $this->body = new Stream();
        $contents = (string) $original;
        if ($contents !== '') {
            $this->body->write($contents);
            $this->body->rewind();
        }
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

    public function json(array $data, int $status = 200): static
    {
        $clone = $this->withStatus($status)
                      ->withHeader('Content-Type', 'application/json');
        $clone->getBody()->write(json_encode($data));
        return $clone;
    }

    public function text(string $data, int $status = 200): static
    {
        $clone = $this->withStatus($status)
                      ->withHeader('Content-Type', 'text/plain');
        $clone->getBody()->write($data);
        return $clone;
    }

    public function html(string $data, int $status = 200): static
    {
        $clone = $this->withStatus($status)
                      ->withHeader('Content-Type', 'text/html; charset=UTF-8');
        $clone->getBody()->write($data);
        return $clone;
    }

    public function noContent(): static
    {
        return $this->withStatus(204);
    }

    public function created(array $data = []): static
    {
        $clone = $this->withStatus(201)
                      ->withHeader('Content-Type', 'application/json');
        $clone->getBody()->write(json_encode($data));
        return $clone;
    }

    public function redirect(string $url, int $status = 302): static
    {
        $url = str_replace(["\r", "\n"], '', $url);
        return $this->withStatus($status)
                    ->withHeader('Location', $url);
    }
}
