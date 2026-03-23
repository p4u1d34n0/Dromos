<?php

namespace Dromos\Http;

use Dromos\Http\Message\UriInterface;

class Uri implements UriInterface
{
    protected string $scheme = 'http', $host = '', $path = '/', $query = '', $fragment = '';

    public function __construct(string $uri = '')
    {
        $parts = parse_url($uri);
        $this->scheme   = $parts['scheme']   ?? $this->scheme;
        $this->host     = $parts['host']     ?? $this->host;
        $this->path     = $parts['path']     ?? $this->path;
        $this->query    = $parts['query']    ?? '';
        $this->fragment = $parts['fragment'] ?? '';
    }
    public function getScheme(): string
    {
        return $this->scheme;
    }
    public function getAuthority(): string
    {
        $port = $this->getPort();
        return $this->host . ($port ? ":$port" : '');
    }
    public function getUserInfo(): string
    {
        return '';
    }
    public function getHost(): string
    {
        return $this->host;
    }
    public function getPort(): ?int
    {
        return null;
    }
    public function getPath(): string
    {
        return $this->path;
    }
    public function getQuery(): string
    {
        return $this->query;
    }
    public function getFragment(): string
    {
        return $this->fragment;
    }
    public function withScheme(string $scheme): static
    {
        $c = clone $this;
        $c->scheme = $scheme;
        return $c;
    }
    public function withUserInfo(string $u, ?string $p = null): static
    {
        return $this;
    }
    public function withHost(string $host): static
    {
        $c = clone $this;
        $c->host = $host;
        return $c;
    }
    public function withPort(?int $p): static
    {
        return $this;
    }
    public function withPath(string $path): static
    {
        $c = clone $this;
        $c->path = $path;
        return $c;
    }
    public function withQuery(string $q): static
    {
        $c = clone $this;
        $c->query = $q;
        return $c;
    }
    public function withFragment(string $f): static
    {
        $c = clone $this;
        $c->fragment = $f;
        return $c;
    }
    public function __toString(): string
    {
        $uri = ($this->scheme ? '' . $this->scheme . '://' : '') . $this->host . $this->path;
        return $uri . ($this->query ? '?' . $this->query : '') . ($this->fragment ? '#' . $this->fragment : '');
    }
}
