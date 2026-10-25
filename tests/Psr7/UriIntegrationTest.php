<?php

declare(strict_types=1);

namespace Dromos\Tests\Psr7;

use Dromos\Http\Uri;
use Http\Psr7Test\UriIntegrationTest as Suite;

final class UriIntegrationTest extends Suite
{
    public function createUri($uri)
    {
        return new Uri($uri);
    }
}
