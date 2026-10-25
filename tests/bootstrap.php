<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// Which factories the PSR-17 suite (http-interop/http-factory-tests) and the
// PSR-7 suite's helpers (php-http/psr7-integration-tests) test and build with
foreach (['REQUEST_FACTORY', 'RESPONSE_FACTORY', 'SERVER_REQUEST_FACTORY', 'STREAM_FACTORY', 'UPLOADED_FILE_FACTORY', 'URI_FACTORY'] as $factory) {
    define($factory, Dromos\Http\HttpFactory::class);
}
