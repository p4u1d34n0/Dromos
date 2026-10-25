<?php

namespace Dromos\Http\Message;

use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * A PSR-7 response whose with*() methods return the same class.
 */
interface ResponseInterface extends MessageInterface, PsrResponseInterface
{
    public function withStatus(int $code, string $reasonPhrase = ''): static;
}
