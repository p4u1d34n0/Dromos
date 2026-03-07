<?php

namespace Dromos\Traits;

use Dromos\Http\Message\StreamInterface;
use Dromos\Http\Message\MessageInterface;

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
        $key = strtolower($name);
        foreach ($this->headers as $h => $_) {
            if (strtolower($h) === $key) {
                return true;
            }
        }
        return false;
    }

    public function getHeader(string $name): array
    {
        $key = strtolower($name);
        foreach ($this->headers as $h => $values) {
            if (strtolower($h) === $key) {
                return $values;
            }
        }
        return [];
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
        $clone->headers[$name] = $values;
        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $val = is_array($value) ? array_values($value) : [(string)$value];
        $this->validateHeaderNameAndValue($name, $val);
        $clone = clone $this;
        if (isset($clone->headers[$name])) {
            $clone->headers[$name] = array_merge($clone->headers[$name], $val);
        } else {
            $clone->headers[$name] = $val;
        }
        return $clone;
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
        foreach ($clone->headers as $h => $_) {
            if (strtolower($h) === strtolower($name)) {
                unset($clone->headers[$h]);
                break;
            }
        }
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
