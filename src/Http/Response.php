<?php

namespace Dromos\Http;

use Dromos\Enums\HttpStatusCodes;
use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Message\Stream;
use Dromos\Traits\MessageTrait;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class Response
 *
 * @package Dromos\Http
 */

class Response implements ResponseInterface
{
    use MessageTrait;

    protected int $statusCode = 200;

    /** Empty means the standard phrase for the status code. */
    protected string $reasonPhrase = '';

    /**
     * @throws \InvalidArgumentException If the code is outside 100 to 599
     */
    public function __construct(int $code = 200, string $reason = '')
    {
        $this->statusCode   = $code >= 100 && $code <= 599 ? $code : self::invalidStatus($code);
        $this->reasonPhrase = $reason;
    }

    /**
     * A Dromos response with the status, headers and body of any PSR-7
     * response, such as one a PSR-15 middleware built with its own factory.
     * A Dromos response is returned as it is.
     */
    public static function fromPsr(PsrResponseInterface $response): ResponseInterface
    {
        if ($response instanceof ResponseInterface) {
            return $response;
        }
        $copy = new self($response->getStatusCode(), $response->getReasonPhrase());
        $copy->protocolVersion = $response->getProtocolVersion();
        $copy->headers = $response->getHeaders();
        $copy->body = $response->getBody();
        return $copy;
    }

    /**
     * Clones get their own copy of an in-memory body, so writing to one never
     * changes the other. Other streams (files, sockets) are shared, as PSR-7
     * allows, rather than read into memory.
     */
    public function __clone()
    {
        if (isset($this->body) && $this->body instanceof Stream) {
            $this->body = clone $this->body; // content and position; PHP copies the string only if it changes
        }
    }

    /**
     * The body. An empty stream is created the first time it's asked for, so
     * a response whose body is replaced (e.g. by json()) never builds one.
     */
    public function getBody(): StreamInterface
    {
        return $this->body ??= new Stream();
    }

    /**
     * Callers check the range inline, as every response is built through
     * them, and call this only for a code outside 100 to 599.
     */
    private static function invalidStatus(int $code): never
    {
        throw new \InvalidArgumentException("Invalid HTTP status code $code: it must be from 100 to 599.");
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    /**
     * @throws \InvalidArgumentException If the code is outside 100 to 599
     */
    public function withStatus(int $code, string $reason = ''): static
    {
        $c = clone $this;
        $c->statusCode = $code >= 100 && $code <= 599 ? $code : self::invalidStatus($code);
        $c->reasonPhrase = $reason;
        return $c;
    }
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase !== ''
            ? $this->reasonPhrase
            : (HttpStatusCodes::HTTP_STATUS_CODES[$this->statusCode] ?? '');
    }

    /**
     * @param int $flags json_encode() flags; JSON_THROW_ON_ERROR is always added
     * @throws \JsonException If the data can't be encoded
     */
    public function json(array $data, int $status = 200, int $flags = 0): static
    {
        return $this->withPayload($status, 'application/json', json_encode($data, $flags | JSON_THROW_ON_ERROR));
    }

    public function text(string $data, int $status = 200): static
    {
        return $this->withPayload($status, 'text/plain', $data);
    }

    public function html(string $data, int $status = 200): static
    {
        return $this->withPayload($status, 'text/html; charset=UTF-8', $data);
    }

    public function noContent(): static
    {
        return $this->withStatus(204);
    }

    /**
     * @throws \JsonException If the data can't be encoded
     */
    public function created(array $data = []): static
    {
        return $this->json($data, 201);
    }

    /**
     * A copy with this status, Content-Type and body (replacing any body it had).
     * The content type is a trusted constant, so header validation is skipped.
     */
    private function withPayload(int $status, string $contentType, string $content): static
    {
        $clone = clone $this;
        $clone->statusCode = $status >= 100 && $status <= 599 ? $status : self::invalidStatus($status);
        $clone->reasonPhrase = '';
        $clone->putHeader('Content-Type', [$contentType]);
        $clone->body = new Stream($content);
        $clone->body->seek(strlen($content));
        return $clone;
    }

    /**
     * Create a redirect response.
     *
     * Only allows relative paths (starting with /) and http/https URLs.
     * Rejects protocol-relative URLs and non-http(s) schemes.
     *
     * @throws \InvalidArgumentException For invalid redirect URLs
     */
    public function redirect(string $url, int $status = 302): static
    {
        $url = str_replace(["\r", "\n"], '', $url);

        if (str_starts_with($url, '//')) {
            throw new \InvalidArgumentException('Protocol-relative redirect URLs are not allowed.');
        }

        if (!str_starts_with($url, '/')) {
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if ($scheme !== null && !in_array(strtolower($scheme), ['http', 'https'], true)) {
                throw new \InvalidArgumentException('Redirect URL must use http or https scheme.');
            }
        }

        return $this->withStatus($status)
                    ->withHeader('Location', $url);
    }
}
