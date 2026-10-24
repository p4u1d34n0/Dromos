<?php

declare(strict_types=1);

namespace Dromos\Tests\Psr7;

use Dromos\Http\Request;
use Dromos\Http\Response;
use Http\Psr7Test\ServerRequestIntegrationTest as Suite;

final class ServerRequestIntegrationTest extends Suite
{
    public function createSubject()
    {
        return new Request();
    }
}
