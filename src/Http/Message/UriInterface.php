<?php

namespace Dromos\Http\Message;

use Psr\Http\Message\UriInterface as PsrUriInterface;

/**
 * A PSR-7 URI whose with*() methods return the same class.
 */
interface UriInterface extends PsrUriInterface
{
    public function withScheme(string $scheme): static;
    public function withUserInfo(string $user, ?string $password = null): static;
    public function withHost(string $host): static;
    public function withPort(?int $port): static;
    public function withPath(string $path): static;
    public function withQuery(string $query): static;
    public function withFragment(string $fragment): static;
}
