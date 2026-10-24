<?php

declare(strict_types=1);

namespace Dromos\Http;

use Dromos\Http\Message\ResourceStream;
use Dromos\Http\Message\Stream;
use Dromos\Http\Message\UploadedFile;
use InvalidArgumentException;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * PSR-17 factories for Dromos's messages, for PSR-15 middleware and other
 * libraries that create requests, responses or streams through PSR-17.
 */
final class HttpFactory implements
    RequestFactoryInterface,
    ResponseFactoryInterface,
    ServerRequestFactoryInterface,
    StreamFactoryInterface,
    UploadedFileFactoryInterface,
    UriFactoryInterface
{
    /** fopen() modes: r, w, a, x or c, then any of b, t and +, in any order. */
    private const MODE = '/^[rwaxc][bt+]*$/D';

    /**
     * A Dromos request is always a server request, with no server params.
     */
    public function createRequest(string $method, $uri): Request
    {
        return $this->createServerRequest($method, $uri);
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): Response
    {
        return new Response($code, $reasonPhrase);
    }

    /**
     * Only what's given: unlike new Request(), nothing is read from $_SERVER,
     * $_GET, $_POST, $_COOKIE, $_FILES or the request body.
     *
     * @param UriInterface|string  $uri
     * @param array<string, mixed> $serverParams
     */
    public function createServerRequest(string $method, $uri, array $serverParams = []): Request
    {
        $request = Request::create('GET', '/', $serverParams)->withMethod($method);
        return $request->withUri(is_string($uri) ? new Uri($uri) : $uri);
    }

    /** An in-memory stream, positioned at the start of its content. */
    public function createStream(string $content = ''): Stream
    {
        return new Stream($content);
    }

    /**
     * @throws InvalidArgumentException If the mode isn't an fopen() mode
     * @throws RuntimeException         If the file can't be opened
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): ResourceStream
    {
        if (!preg_match(self::MODE, $mode)) {
            throw new InvalidArgumentException("Invalid file mode \"$mode\".");
        }
        if ($filename === '') {
            throw new RuntimeException('The file name must not be empty.');
        }
        $resource = @fopen($filename, $mode);
        if ($resource === false) {
            throw new RuntimeException("Could not open $filename with mode \"$mode\": " . (error_get_last()['message'] ?? 'unknown error'));
        }
        return new ResourceStream($resource);
    }

    /**
     * @param resource $resource
     */
    public function createStreamFromResource($resource): ResourceStream
    {
        return new ResourceStream($resource);
    }

    /**
     * @throws InvalidArgumentException If the stream isn't readable
     */
    public function createUploadedFile(
        StreamInterface $stream,
        ?int $size = null,
        int $error = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ): UploadedFileInterface {
        if (!$stream->isReadable()) {
            throw new InvalidArgumentException('The uploaded file stream must be readable.');
        }
        return new UploadedFile($stream, $size ?? $stream->getSize(), $error, $clientFilename, $clientMediaType);
    }

    public function createUri(string $uri = ''): Uri
    {
        return new Uri($uri);
    }
}
