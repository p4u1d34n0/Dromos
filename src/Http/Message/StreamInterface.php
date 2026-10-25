<?php

namespace Dromos\Http\Message;

use Psr\Http\Message\StreamInterface as PsrStreamInterface;

/**
 * A PSR-7 stream. Kept so code written against Dromos 1.x still type-checks.
 */
interface StreamInterface extends PsrStreamInterface
{
}
