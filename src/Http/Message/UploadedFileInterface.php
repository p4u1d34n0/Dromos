<?php

namespace Dromos\Http\Message;

use Psr\Http\Message\UploadedFileInterface as PsrUploadedFileInterface;

/**
 * A PSR-7 uploaded file whose stream is a Dromos stream.
 */
interface UploadedFileInterface extends PsrUploadedFileInterface
{
    public function getStream(): StreamInterface;
}
