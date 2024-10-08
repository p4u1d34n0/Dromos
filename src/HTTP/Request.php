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
    /**
     * Constructor for the Request class.
     *
     * @param array $parameters An array of parameters for the request.
     */
    public function __construct(protected array $parameters) {}

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
}
