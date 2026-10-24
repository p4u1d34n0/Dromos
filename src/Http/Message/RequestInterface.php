<?php

namespace Dromos\Http\Message;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\UriInterface as PsrUriInterface;

/**
 * A PSR-7 request whose with*() methods return the same class.
 */
interface RequestInterface extends MessageInterface, PsrRequestInterface
{
    public function withMethod(string $method): static;
    public function withRequestTarget(string $requestTarget): static;
    public function withUri(PsrUriInterface $uri, bool $preserveHost = false): static;
}
