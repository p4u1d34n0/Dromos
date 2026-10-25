<?php

namespace Dromos\Traits;

use Psr\Http\Message\StreamInterface;

trait MessageTrait
{
    /** @var string Protocol version, e.g. "1.1" */
    protected string $protocolVersion = '1.1';

    /** @var array<string, string[]> Map of header name to array of values */
    protected array $headers = [];

    /** @var StreamInterface The message body */
    protected StreamInterface $body;

    // -- MessageInterface methods --

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return $this->headerKey($name) !== null;
    }

    public function getHeader(string $name): array
    {
        $key = $this->headerKey($name);
        return $key === null ? [] : $this->headers[$key];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        $values = is_array($value) ? array_values($value) : [(string)$value];
        $this->validateHeaderNameAndValue($name, $values);
        $clone = clone $this;
        $clone->putHeader($name, $values);
        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $values = is_array($value) ? array_values($value) : [(string)$value];
        $this->validateHeaderNameAndValue($name, $values);
        $clone = clone $this;
        $key = $clone->headerKey($name) ?? $name;
        $clone->headers[$key] = array_merge($clone->headers[$key] ?? [], $values);
        return $clone;
    }

    /**
     * Set a header on this instance, replacing it under any case. For use on
     * clones; the values must already be valid.
     *
     * @param string[] $values
     */
    private function putHeader(string $name, array $values): void
    {
        if ($this->headers !== []) {
            $this->removeHeader($name);
        }
        $this->headers[$name] = $values;
    }

    private function removeHeader(string $name): void
    {
        $key = $this->headerKey($name);
        if ($key !== null) {
            unset($this->headers[$key]);
        }
    }

    /**
     * The key a header is stored under, matching the name case-insensitively
     * as HTTP requires, or null if the message has no such header.
     */
    private function headerKey(string $name): ?string
    {
        if (isset($this->headers[$name])) {
            return $name;
        }
        foreach ($this->headers as $key => $_) {
            if (strcasecmp((string) $key, $name) === 0) {
                return (string) $key;
            }
        }
        return null;
    }

    /**
     * Validate that a header name contains only RFC 7230 token characters
     * and that values do not contain CR or LF characters (prevents HTTP
     * Response Splitting / header injection).
     *
     * @param string   $name   Header name
     * @param string[] $values Header values
     *
     * @throws \InvalidArgumentException
     */
    private function validateHeaderNameAndValue(string $name, array $values): void
    {
        if (preg_match('/[^a-zA-Z0-9\-\_\.\~\!\#\$\&\'\*\+\^`\|]/', $name)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid header name: %s', $name)
            );
        }
        foreach ($values as $value) {
            if (preg_match("/[\r\n]/", (string) $value)) {
                throw new \InvalidArgumentException(
                    sprintf('Header value must not contain CR or LF characters: %s', $name)
                );
            }
        }
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        $clone->removeHeader($name);
        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }
}
