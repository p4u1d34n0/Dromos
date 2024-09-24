<?php

namespace App\HTTP;

class Response
{
    protected array $headers = [];
    protected mixed $body;

    public function json(array $data, int $statusCode = 200): void
    {
        // Set content type header
        header('Content-Type: application/json', true, $statusCode);

        // Set the response headers
        if (!empty($this->headers)) {
            foreach ($this->headers as $key => $value) {
                header($key .':'. $value);
            }
        }

        // Set the response body to the JSON-encoded data
        $this->body = json_encode($data);

        // Output the response
        echo $this->body;
        exit; // End the script after sending response
    }

    public function headers(string $key, mixed $value): void
    {
        $this->headers[$key] = $value;
    }
}
