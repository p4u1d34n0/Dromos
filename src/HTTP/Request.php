<?php

namespace Dromos\HTTP;

/**
 * Class Request
 *
 * Handles HTTP request parameters.
 *
 * @package Router\HTTP
 */
class Request
{

    protected array $headers;
    protected string $method;
    protected string $uri;
    protected array $queryParams;
    protected array $bodyParams;
    protected array $files;

    /**
     * Constructor for the Request class.
     *
     * @param array $parameters An array of parameters for the request.
     */
    public function __construct(protected array $parameters = [])
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->headers = $this->parseHeaders();

        // If custom parameters are provided, use them, otherwise default to $_GET and $_POST
        $this->queryParams = $parameters['query'] ?? $_GET;
        $this->bodyParams = $parameters['body'] ?? $_POST;
        $this->files = $parameters['files'] ?? $_FILES;
    }

    /**
     * Retrieves all headers from the request.
     *
     * @return array An associative array of headers.
     */
    protected function parseHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[strtolower($header)] = $value;
            }
        }
        return $headers;
    }

    /**
     * Get HTTP method (GET, POST, PUT, DELETE, etc.).
     *
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Retrieves a value from headers.
     *
     * @param string $key The key of the header to retrieve.
     * @return string|null The value of the header if it exists, or null if it does not.
     */
    public function getHeader(string $key): ?string
    {
        $key = strtolower($key);
        return $this->headers[$key] ?? null;
    }

    /**
     * Get request URI.
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Retrieves a value from query parameters.
     *
     * @param string $key The key of the query parameter.
     * @return mixed|null The value if it exists, or null if not found.
     */
    public function getQueryParam(string $key): mixed
    {
        return $this->queryParams[$key] ?? null;
    }

    /**
     * Retrieves a value from body parameters (POST data).
     *
     * @param string $key The key of the body parameter.
     * @return mixed|null The value if it exists, or null if not found.
     */
    public function getBodyParam(string $key): mixed
    {
        return $this->bodyParams[$key] ?? null;
    }

    /**
     * Retrieves a value from the request parameters.
     *
     * @param string $key The key of the parameter to retrieve.
     * @return mixed The value of the parameter if it exists, or null if it does not.
     */
    public function get(string $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }

    /**
     * Retrieves a file from uploaded files.
     *
     * @param string $key The key of the file to retrieve.
     * @return mixed The file data if it exists, or null if it does not.
     */
    public function getFile(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }
}
