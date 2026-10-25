<?php

declare(strict_types=1);

namespace Dromos\Tests\Psr7;

use Dromos\Http\Message\ResourceStream;
use Http\Psr7Test\StreamIntegrationTest as Suite;

/** The suite builds every stream it tests from a resource, so it tests ResourceStream. */
final class ResourceStreamIntegrationTest extends Suite
{
    public function createStream($data)
    {
        return is_string($data) ? ResourceStream::fromString($data) : new ResourceStream($data);
    }
}
