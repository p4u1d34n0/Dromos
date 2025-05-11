<?php
// src/Http/RequestInterface.php
namespace Dromos\Http\Message;

use Dromos\Http\Message\UriInterface;

interface RequestInterface extends MessageInterface
{
    public function getMethod(): string;
    public function withMethod(string $method): static;
    public function getRequestTarget(): string;
    public function withRequestTarget(string $target): static;
    public function getUri(): UriInterface;
    public function withUri(UriInterface $uri, bool $preserveHost = false): static;
}
