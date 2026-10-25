<?php

namespace Dromos\Http\Message;

use Psr\Http\Message\UploadedFileInterface as PsrUploadedFileInterface;

/**
 * A PSR-7 uploaded file. Kept so code written against Dromos 1.x still type-checks.
 */
interface UploadedFileInterface extends PsrUploadedFileInterface
{
}
