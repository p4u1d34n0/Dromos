<?php

namespace Dromos\Http\Message;

use Psr\Http\Message\MessageInterface as PsrMessageInterface;
use Psr\Http\Message\StreamInterface as PsrStreamInterface;

/**
 * A PSR-7 message whose with*() methods return the same class.
 */
interface MessageInterface extends PsrMessageInterface
{
    public function withProtocolVersion(string $version): static;
    /** @return array<string, array<string>> Each header's values, by name */
    public function getHeaders(): array;
    public function withHeader(string $name, $value): static;
    public function withAddedHeader(string $name, $value): static;
    public function withoutHeader(string $name): static;
    public function withBody(PsrStreamInterface $body): static;
}
