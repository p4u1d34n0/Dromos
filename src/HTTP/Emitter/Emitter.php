<?php

namespace Dromos\Http\Emitter;

use Dromos\Http\Message\ResponseInterface;
use Dromos\Http\Emitter\EmitterInterface;


/**
 * Standard HTTP Emitter for Dromos
 * 
 * Handles sending PSR-7 responses to the client with proper headers and body content
 */
class Emitter implements EmitterInterface
{
    /**
     * Whether headers have already been sent
     *
     * @var bool
     */
    private bool $headersSent = false;

    /**
     * Output buffer level when the emitter is instantiated
     *
     * @var int
     */
    private int $bufferLevel;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->bufferLevel = ob_get_level();
    }

    /**
     * {@inheritdoc}
     */
    public function emit(ResponseInterface $response): void
    {
        if ($this->isHeadersSent()) {
            throw new \RuntimeException('Headers already sent');
        }

        $this->emitStatusLine($response);
        $this->emitHeaders($response);
        $this->emitBody($response);
    }

    /**
     * Emit the status line
     *
     * @param ResponseInterface $response
     * @return void
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();

        // Send status line with reason phrase if available
        $statusLine = sprintf(
            'HTTP/%s %d%s',
            $response->getProtocolVersion(),
            $statusCode,
            ($reasonPhrase ? ' ' . $reasonPhrase : '')
        );

        header($statusLine, true, $statusCode);
    }

    /**
     * Emit response headers
     *
     * @param ResponseInterface $response
     * @return void
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        foreach ($response->getHeaders() as $name => $values) {
            $this->emitHeader($name, $values);
        }
    }

    /**
     * Emit a specific header with its values
     *
     * @param string $name
     * @param array $values
     * @return void
     */
    private function emitHeader(string $name, array $values): void
    {
        $name = str_replace('-', ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '-', $name);

        foreach ($values as $value) {
            header(sprintf('%s: %s', $name, $value), false);
        }
    }

    /**
     * Emit the response body
     *
     * @param ResponseInterface $response
     * @return void
     */
    private function emitBody(ResponseInterface $response): void
    {
        // Clean any output buffering to prevent unexpected output
        $this->cleanOutputBuffer();

        $body = $response->getBody();

        // If body is empty, return early
        if ($body->getSize() === 0) {
            return;
        }

        // Rewind the stream before reading
        if ($body->isSeekable()) {
            $body->rewind();
        }

        // For large responses, stream the output in chunks to preserve memory
        if ($body->isReadable()) {
            while (!$body->eof()) {
                echo $body->read(8192);

                // Flush after each chunk if output buffering is enabled
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
            return;
        }

        // Fallback: get contents and output
        echo $body->getContents();
    }

    /**
     * Clean output buffer
     *
     * @return void
     */
    private function cleanOutputBuffer(): void
    {
        // Only clean levels that were not present when the emitter was instantiated
        $currentLevel = ob_get_level();

        while ($currentLevel > $this->bufferLevel) {
            ob_end_clean();
            $currentLevel--;
        }
    }

    /**
     * Check if headers have already been sent
     *
     * @return bool
     */
    private function isHeadersSent(): bool
    {
        if ($this->headersSent) {
            return true;
        }

        $this->headersSent = headers_sent();
        return $this->headersSent;
    }
}
