<?php

declare(strict_types=1);

namespace Dromos\Tests\Psr7;

use Dromos\Http\Message\Stream;
use Dromos\Http\Message\UploadedFile;
use Http\Psr7Test\UploadedFileIntegrationTest as Suite;

final class UploadedFileIntegrationTest extends Suite
{
    public function createSubject()
    {
        return new UploadedFile(new Stream('Hello, world'), 12, UPLOAD_ERR_OK, 'hello.txt', 'text/plain');
    }
}
