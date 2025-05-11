<?php

namespace Dromos\Http;

use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\StreamInterface;
use Dromos\Http\Message\UriInterface;

class Request implements ServerRequestInterface
{
    use MessageTrait;

    protected string $method;
    protected UriInterface $uri;
    protected array $serverParams;
    protected array $cookieParams;
    protected array $queryParams;
    protected array $uploadedFiles;
    protected null|array|object $parsedBody;
    protected array $attributes = [];

    public function __construct()
    {
        $this->method        = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri           = new Uri($_SERVER['REQUEST_URI'] ?? '/');
        $this->serverParams  = $_SERVER;
        $this->cookieParams  = $_COOKIE;
        $this->queryParams   = $_GET;
        $this->parsedBody    = $_POST;
        $this->uploadedFiles = $_FILES;
        $this->body          = new Stream();
    }

    // -- RequestInterface methods (from PSR-7) --

    public function getRequestTarget(): string
    {
        return $this->uri->getPath() . ($this->uri->getQuery() ? '?' . $this->uri->getQuery() : '');
    }
    public function withRequestTarget(string $target): static
    {
        // you could parse target into path/query here
        $clone = clone $this;
        return $clone;
    }
    public function getMethod(): string
    {
        return $this->method;
    }
    public function withMethod(string $method): static
    {
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }
    public function getUri(): UriInterface
    {
        return $this->uri;
    }
    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->uri = $uri;
        return $clone;
    }

    // -- ServerRequestInterface methods --

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }
    public function withCookieParams(array $cookies): static
    {
        $clone = clone $this;
        $clone->cookieParams = $cookies;
        return $clone;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }
    public function withQueryParams(array $query): static
    {
        $clone = clone $this;
        $clone->queryParams = $query;
        return $clone;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }
    public function withUploadedFiles(array $files): static
    {
        $clone = clone $this;
        $clone->uploadedFiles = $files;
        return $clone;
    }

    public function getParsedBody(): null|array|object
    {
        return $this->parsedBody;
    }
    public function withParsedBody(null|array|object $data): static
    {
        $clone = clone $this;
        $clone->parsedBody = $data;
        return $clone;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }
    public function withAttribute(string $name, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }
    public function withoutAttribute(string $name): static
    {
        $clone = clone $this;
        unset($clone->attributes[$name]);
        return $clone;
    }
}
