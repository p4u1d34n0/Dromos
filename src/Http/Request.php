<?php

namespace Dromos\Http;

use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\Stream;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface as PsrServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use Dromos\Traits\MessageTrait;

/**
 * Class Request
 *
 * This class implements the PSR-7 ServerRequestInterface.
 * It represents an HTTP request and provides methods to access
 * request data such as headers, query parameters, and body content.
 *
 * @package Dromos\Http
 * @see https://www.php-fig.org/psr/psr-7/
 * @see https://www.php-fig.org/psr/psr-15/
 */

class Request implements ServerRequestInterface
{
    use MessageTrait;

    /** Largest request body read, in bytes; a larger body is dropped. */
    private const MAX_BODY_BYTES = 1048576;

    protected string $method;
    protected UriInterface $uri;
    protected array $serverParams;
    protected array $cookieParams;
    protected array $queryParams;
    protected array $uploadedFiles;
    protected null|array|object $parsedBody;
    protected array $attributes = [];
    protected ?string $requestTarget = null;

    /** False until the body has been read from php://input (see loadBody()). */
    private bool $bodyLoaded = false;

    /**
     * Build the request from PHP's globals. The body is read from php://input
     * the first time it, or the parsed body, is asked for, so requests whose
     * handlers never look at it skip the read.
     */
    public function __construct()
    {
        $this->method        = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri           = Uri::fromServerParams($_SERVER);
        $this->serverParams  = $_SERVER;
        $this->cookieParams  = $_COOKIE;
        $this->queryParams   = $_GET;
        $this->parsedBody    = self::isFormPost($_SERVER) ? $_POST : null;
        $this->uploadedFiles = $_FILES;
    }

    /**
     * True for a POST whose body PHP parsed into $_POST: a form or multipart
     * body. PSR-7 gives other requests a null parsed body (or, here, a
     * decoded JSON body).
     *
     * @param array<string, mixed> $server
     */
    private static function isFormPost(array $server): bool
    {
        if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }
        $type = strtolower((string) ($server['CONTENT_TYPE'] ?? ''));
        return str_starts_with($type, 'application/x-www-form-urlencoded') || str_starts_with($type, 'multipart/form-data');
    }

    /**
     * Create a Request with explicit parameters, useful for testing.
     */
    public static function create(
        string $method = 'GET',
        string $uri = '/',
        array $serverParams = [],
        array $headers = [],
        array $queryParams = [],
        null|array|object $parsedBody = null,
        string $body = ''
    ): static {
        $request = new static();
        $request->method = $method;
        $request->uri = new Uri($uri);
        $request->serverParams = $serverParams;
        $request->cookieParams = [];
        $request->queryParams = $queryParams;
        $request->parsedBody = $parsedBody;
        $request->uploadedFiles = [];
        $request->body = new Stream($body);
        $request->bodyLoaded = true;
        foreach ($headers as $name => $value) {
            $request->headers[$name] = is_array($value) ? $value : [(string)$value];
        }
        return $request;
    }

    /**
     * A Dromos request with everything a PSR-7 server request holds, such as
     * one a PSR-15 middleware built with another library. A Dromos request is
     * returned as it is.
     */
    public static function fromPsr(PsrServerRequestInterface $request): ServerRequestInterface
    {
        if ($request instanceof ServerRequestInterface) {
            return $request;
        }
        $copy = new self();
        $copy->method = $request->getMethod();
        $copy->uri = $request->getUri();
        $copy->requestTarget = $request->getRequestTarget();
        $copy->protocolVersion = $request->getProtocolVersion();
        $copy->headers = $request->getHeaders();
        $copy->body = $request->getBody();
        $copy->bodyLoaded = true;
        $copy->serverParams = $request->getServerParams();
        $copy->cookieParams = $request->getCookieParams();
        $copy->queryParams = $request->getQueryParams();
        $copy->uploadedFiles = $request->getUploadedFiles();
        $copy->parsedBody = $request->getParsedBody();
        $copy->attributes = $request->getAttributes();
        return $copy;
    }

    // -- RequestInterface methods (from PSR-7) --

    public function getRequestTarget(): string
    {
        if (isset($this->requestTarget)) {
            return $this->requestTarget;
        }
        return $this->uri->getPath() . ($this->uri->getQuery() ? '?' . $this->uri->getQuery() : '');
    }
    public function withRequestTarget(string $target): static
    {
        $clone = clone $this;
        $clone->requestTarget = $target;
        return $clone;
    }
    public function getMethod(): string
    {
        return $this->method;
    }
    /**
     * @throws InvalidArgumentException If the method isn't an HTTP token, such as GET
     */
    public function withMethod(string $method): static
    {
        if (!preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/D', $method)) {
            throw new InvalidArgumentException(sprintf('Invalid HTTP method: "%s"', $method));
        }
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }
    public function getUri(): UriInterface
    {
        return $this->uri;
    }
    /**
     * With $preserveHost false, the Host header becomes the new URI's host,
     * if it has one. With true, it's set only if the request has none.
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        $clone = clone $this;
        $clone->uri = $uri;
        $host = $uri->getHost();
        if ($host !== '' && (!$preserveHost || $clone->getHeaderLine('Host') === '')) {
            $port = $uri->getPort();
            $clone->putHeader('Host', [$port === null ? $host : "$host:$port"]);
        }
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
        $this->loadBody();
        return $this->parsedBody;
    }
    public function withParsedBody($data): static
    {
        if ($data !== null && !is_array($data) && !is_object($data)) {
            throw new InvalidArgumentException('The parsed body must be null, an array or an object, got ' . get_debug_type($data) . '.');
        }
        // The copy reads the body, if it's still to be read, so this request is unchanged
        $clone = clone $this;
        $clone->loadBody();
        $clone->parsedBody = $data;
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        $this->loadBody();
        return $this->body;
    }
    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->loadBody();
        $clone->body = $body;
        return $clone;
    }

    /**
     * Read the body, at most MAX_BODY_BYTES of it, and decode a JSON body into
     * the parsed body. Runs once; a request that is never asked for its body
     * never reads it.
     */
    private function loadBody(): void
    {
        if ($this->bodyLoaded) {
            return;
        }
        $this->bodyLoaded = true;

        // A declared length over the limit is dropped without reading; without
        // one (e.g. chunked), read one byte past the limit to detect overflow
        $raw = (int) ($this->serverParams['CONTENT_LENGTH'] ?? 0) > self::MAX_BODY_BYTES
            ? ''
            : $this->readInput(self::MAX_BODY_BYTES + 1);
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            $raw = '';
        }
        $this->body = new Stream($raw);

        if ($raw !== '' && str_contains($this->serverParams['CONTENT_TYPE'] ?? '', 'application/json')) {
            $decoded = json_decode($raw, true, 64);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->parsedBody = $decoded;
            }
        }
    }

    /**
     * Up to $maxBytes of the raw request body.
     */
    protected function readInput(int $maxBytes): string
    {
        $stream = @fopen('php://input', 'rb');
        if ($stream === false) {
            return '';
        }
        $raw = stream_get_contents($stream, $maxBytes);
        fclose($stream);
        return $raw === false ? '' : $raw;
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
