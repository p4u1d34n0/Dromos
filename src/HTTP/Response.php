<?php

namespace Dromos\HTTP;

use Dromos\Enums\StatusCodes;

class Response
{
    protected array $headers = [];
    protected mixed $body;
    protected int $statusCode = 200;

    /**
     * Sends a JSON response.
     *
     * @param array $data The data to send as JSON.
     * @param int $statusCode The HTTP status code (default: 200).
     */
    public function json(array $data, int $statusCode = 200): void
    {
        // Set content type header
        header(
            header: 'Content-Type: application/json',
            replace: true,
            response_code: $statusCode
        );

        // Set the response headers
        if (!empty($this->headers)) {
            foreach ($this->headers as $key => $value) {
                header(header: $key . ':' . $value);
            }
        }

        // Set the response body to the JSON-encoded data
        $this->body = json_encode(value: $data);

        // Output the response
        echo $this->body;
        exit; // End the script after sending response
    }

    /**
     * Sends a plain text or HTML response.
     *
     * @param string $body The response body.
     * @param int $statusCode The HTTP status code (default: 200).
     */
    public function send(string $body, int $statusCode = 200): void
    {
        $this->statusCode = $statusCode;
        $this->headers(key: 'Content-Type', value: 'text/html');
        $this->sendBody(body: $body);
    }

    /**
     * Sends the headers and the body to the client.
     *
     * @param string $body The response body to send.
     */
    protected function sendBody(string $body): void
    {
        // Send the response headers
        if (!empty($this->headers)) {
            foreach ($this->headers as $key => $value) {
                header(header: "{$key}: {$value}", replace: true, response_code: $this->statusCode);
            }
        }

        // Set the body
        $this->body = $body;

        // Output the body
        echo $this->body;
        exit; // Ensure no further output is sent after the response
    }

    /**
     * Sends a status code response with an optional message.
     *
     * @param int $statusCode The HTTP status code.
     * @param string|null $message Optional message to send with the status.
     */
    public function sendStatus(int $statusCode, ?string $message = null): void
    {
        $this->statusCode = $statusCode;

        // Use a default message for common status codes if none provided
        if ($message === null) {
            $message = $this->getStatusMessage(statusCode: $statusCode);
        }

        $this->headers(key: 'Content-Type', value: 'text/plain');
        $this->sendBody(body: $message);
    }

    /**
     * Retrieves a default message for common HTTP status codes.
     *
     * @param int $statusCode The HTTP status code.
     * @return string The associated message for the status code.
     */
    protected function getStatusMessage(int $statusCode): string
    {

        return StatusCodes::HTTP_STATUS_CODES[$statusCode] ?? 'Unknown Status';
    }

    public function headers(string $key, mixed $value): void
    {
        $this->headers[$key] = $value;
    }
}
