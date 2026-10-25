<?php

declare(strict_types=1);

namespace Dromos\Http;

use Dromos\Http\Message\UriInterface;
use InvalidArgumentException;

/**
 * A URI as PSR-7 defines it (RFC 3986): scheme and host are lowercased, the
 * port is dropped when it's the scheme's default, and characters that aren't
 * allowed in a component are percent-encoded, while existing encodings are
 * kept.
 */
class Uri implements UriInterface
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /** Characters a path may hold unencoded: unreserved, sub-delims, ":", "@" and "/". */
    private const PATH_CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-._~!$&\'()*+,;=:@/';

    protected string $scheme = '';
    protected string $userInfo = '';
    protected string $host = '';
    protected ?int $port = null;
    protected string $path = '';
    protected string $query = '';
    protected string $fragment = '';

    /**
     * The $_SERVER-style parameters whose scheme, host and port are still to
     * be read (see fromServerParams()), or null once they have been.
     *
     * @var array<string, mixed>|null
     */
    private ?array $server = null;

    /**
     * @throws InvalidArgumentException If the URI can't be parsed
     */
    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            return;
        }
        $parts = parse_url($uri);
        if ($parts === false) {
            throw new InvalidArgumentException("Unable to parse the URI \"$uri\".");
        }
        $this->scheme = isset($parts['scheme']) ? self::scheme($parts['scheme']) : '';
        $this->userInfo = isset($parts['user']) ? self::userInfo($parts['user'], $parts['pass'] ?? null) : '';
        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port = isset($parts['port']) ? self::port($parts['port']) : null;
        $this->path = isset($parts['path']) ? self::path($parts['path']) : '';
        $this->query = isset($parts['query']) ? self::queryOrFragment($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? self::queryOrFragment($parts['fragment']) : '';
    }

    /**
     * The URI of the current request, from $_SERVER-style parameters: the
     * scheme from HTTPS, the host and port from the Host header (or
     * SERVER_NAME and SERVER_PORT), and the path and query from REQUEST_URI.
     *
     * @param array<string, mixed> $server
     */
    public static function fromServerParams(array $server): self
    {
        // A router needs only the path, so the scheme, host and port are read
        // when they are first asked for, by resolveAuthority()
        $uri = new self();
        $uri->server = $server;

        $target = $server['REQUEST_URI'] ?? '';
        if (!is_string($target) || $target === '') {
            $target = '/';
        }
        $question = strpos($target, '?');
        if ($question === false) {
            $uri->path = self::path($target);
        } else {
            $uri->path = self::path(substr($target, 0, $question));
            $uri->query = self::queryOrFragment(substr($target, $question + 1));
        }
        return $uri;
    }

    public function getScheme(): string
    {
        if ($this->server !== null) {
            $this->resolveAuthority();
        }
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->server !== null) {
            $this->resolveAuthority();
        }
        if ($this->host === '') {
            return '';
        }
        $port = $this->getPort();
        return ($this->userInfo === '' ? '' : $this->userInfo . '@') . $this->host . ($port === null ? '' : ':' . $port);
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        if ($this->server !== null) {
            $this->resolveAuthority();
        }
        return $this->host;
    }

    /** Null when no port is set, or when it's the scheme's default. */
    public function getPort(): ?int
    {
        if ($this->server !== null) {
            $this->resolveAuthority();
        }
        return $this->port === (self::DEFAULT_PORTS[$this->scheme] ?? null) ? null : $this->port;
    }

    /**
     * Leading slashes are reduced to one, so the path can't be read as an
     * authority ("//evil.example/") when used on its own.
     */
    public function getPath(): string
    {
        return str_starts_with($this->path, '//') ? '/' . ltrim($this->path, '/') : $this->path;
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
        if ($this->server !== null) {
            $this->resolveAuthority();
        }
        $clone = clone $this;
        $clone->scheme = self::scheme($scheme);
        return $clone;
    }

    public function withUserInfo(string $user, ?string $password = null): static
    {
        $clone = clone $this;
        $clone->userInfo = self::userInfo($user, $password);
        return $clone;
    }

    public function withHost(string $host): static
    {
        if ($this->server !== null) {
            $this->resolveAuthority();
        }
        $clone = clone $this;
        $clone->host = strtolower($host);
        return $clone;
    }

    public function withPort(?int $port): static
    {
        if ($this->server !== null) {
            $this->resolveAuthority();
        }
        $clone = clone $this;
        $clone->port = $port === null ? null : self::port($port);
        return $clone;
    }

    public function withPath(string $path): static
    {
        $clone = clone $this;
        $clone->path = self::path($path);
        return $clone;
    }

    public function withQuery(string $query): static
    {
        $clone = clone $this;
        $clone->query = self::queryOrFragment(ltrim($query, '?'));
        return $clone;
    }

    public function withFragment(string $fragment): static
    {
        $clone = clone $this;
        $clone->fragment = self::queryOrFragment(ltrim($fragment, '#'));
        return $clone;
    }

    /** The URI as RFC 3986 section 5.3 composes it. */
    public function __toString(): string
    {
        if ($this->server !== null) {
            $this->resolveAuthority();
        }
        $uri = $this->scheme === '' ? '' : $this->scheme . ':';
        $authority = $this->getAuthority();
        $path = $this->path;
        if ($authority !== '') {
            $uri .= '//' . $authority;
            if ($path !== '' && $path[0] !== '/') {
                $path = '/' . $path;
            }
        } elseif (str_starts_with($path, '//')) {
            $path = '/' . ltrim($path, '/');
        }
        $uri .= $path;
        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }
        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }
        return $uri;
    }

    /**
     * Read the scheme, host and port from the parameters fromServerParams()
     * was given: the scheme from HTTPS, and the host and port from the Host
     * header, or SERVER_NAME and SERVER_PORT.
     */
    private function resolveAuthority(): void
    {
        $server = $this->server ?? [];
        $this->server = null;

        $https = $server['HTTPS'] ?? '';
        $this->scheme = (is_string($https) ? $https !== '' && $https !== 'off' : (bool) $https) ? 'https' : 'http';

        $host = $server['HTTP_HOST'] ?? '';
        if (!is_string($host) || $host === '') {
            $host = $server['SERVER_NAME'] ?? '';
            $host = is_string($host) ? $host : '';
        }
        if ($host === '') {
            return;
        }
        $port = $server['SERVER_PORT'] ?? null;
        $port = is_int($port) ? $port : (is_string($port) && $port !== '' ? (int) $port : null);
        // "[::1]:8080" and "example.com:8080": a port follows the last colon after any IPv6 brackets
        $colon = strrpos($host, ':');
        if ($colon !== false && $colon > (int) strrpos($host, ']')) {
            $port = (int) substr($host, $colon + 1);
            $host = substr($host, 0, $colon);
        }
        $this->host = strtolower($host);
        // The Host header comes from the client, so a port out of range is dropped rather than thrown
        $this->port = $port !== null && $port >= 0 && $port <= 65535 ? $port : null;
    }

    /**
     * @throws InvalidArgumentException If it isn't a letter followed by letters, digits, "+", "-" or "."
     */
    private static function scheme(string $scheme): string
    {
        if ($scheme !== '' && !preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*$/D', $scheme)) {
            throw new InvalidArgumentException("Invalid URI scheme \"$scheme\".");
        }
        return strtolower($scheme);
    }

    private static function userInfo(string $user, ?string $password): string
    {
        $encode = static fn(string $part): string => self::encode($part, '/[^a-zA-Z0-9_\-.~!$&\'()*+,;=%]++|%(?![a-fA-F0-9]{2})/');
        return $encode($user) . ($password === null || $password === '' ? '' : ':' . $encode($password));
    }

    /**
     * @throws InvalidArgumentException If it's outside 0 to 65535
     */
    private static function port(int $port): int
    {
        return $port >= 0 && $port <= 65535 ? $port : throw new InvalidArgumentException("Invalid port $port: it must be from 0 to 65535.");
    }

    private static function path(string $path): string
    {
        // Most paths need no encoding, so they skip the regex
        if (strspn($path, self::PATH_CHARACTERS) === strlen($path)) {
            return $path;
        }
        return self::encode($path, '/[^a-zA-Z0-9_\-.~!$&\'()*+,;=%:@\/]++|%(?![a-fA-F0-9]{2})/');
    }

    private static function queryOrFragment(string $value): string
    {
        if (strspn($value, self::PATH_CHARACTERS . '?') === strlen($value)) {
            return $value;
        }
        return self::encode($value, '/[^a-zA-Z0-9_\-.~!$&\'()*+,;=%:@\/?]++|%(?![a-fA-F0-9]{2})/');
    }

    /** Percent-encode what $pattern matches, leaving existing %XX encodings alone. */
    private static function encode(string $value, string $pattern): string
    {
        return (string) preg_replace_callback($pattern, static fn(array $match): string => rawurlencode($match[0]), $value);
    }
}
