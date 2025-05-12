<?php

namespace Dromos;

/**
 * Class RouterException
 *
 * This class represents exceptions thrown during routing.
 * It extends \Exception and works with RouterExceptionHandler to produce 
 * appropriate HTTP responses.
 */
class RouterException extends \Exception
{
    /**
     * @var int HTTP status code
     */
    protected int $statusCode;

    /**
     * @var array Additional arguments
     */
    protected array $args;

    /**
     * Constructor
     *
     * @param string $message Exception message
     * @param int $statusCode HTTP status code
     * @param array $args Additional arguments
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = "",
        int $statusCode = 500,
        array $args = [],
        \Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->args = $args;
    }

    /**
     * Get the HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get additional arguments
     *
     * @return array
     */
    public function getArgs(): array
    {
        return $this->args;
    }

    /**
     * Creates a "Method Not Allowed" exception
     *
     * @param array $available Available HTTP methods
     * @return static
     */
    public static function methodNotAllowed(array $available = []): self
    {
        $message = 'Method not allowed';
        if (!empty($available)) {
            $message .= '. Available methods: ' . implode(', ', $available);
        }

        return new self($message, 405, ['available_methods' => $available]);
    }

    /**
     * Creates a "Route Not Found" exception
     *
     * @param string|null $path The route path that wasn't found
     * @return static
     */
    public static function routeNotFound(?string $path = null): self
    {
        $message = 'Route not found';
        if ($path) {
            $message .= ': ' . $path;
        }

        return new self($message, 404);
    }

    /**
     * Creates a "Target Not Found" exception
     *
     * @param array|null $missing Missing routes information
     * @return static
     */
    public static function targetNotFound(?array $missing = null): self
    {
        $missingRoutes = [];

        if ($missing) {
            $missingRoutes = array_map(function ($route): array {
                return [$route[0]::class => $route[1]];
            }, $missing);

            return new self('Missing Target Methods', 404, ['missing_routes' => $missingRoutes]);
        }

        return new self('Target not found', 404);
    }
}
