<?php

namespace Dromos;

use Dromos\Router;
use Dromos\RouterException;

/**
 * Class RouteResource
 *
 * This class is responsible for defining and managing route resources in a web application.
 * It allows specifying which HTTP methods should be included or excluded for a given route.
 *
 * @package Router
 */
class RouteResource
{

    // Define all methods to be excluded by default
    protected $excludedMethods = ['OPTIONS', 'HEAD'];

    // Define all methods by default
    protected $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];


    /**
     * Constructor for the RouteResource class.
     *
     * @param string $url The URL pattern for the route.
     * @param string $controller The controller associated with the route.
     */
    public function __construct(protected string $url, protected string $controller) {}


    /**
     * Excludes the specified HTTP methods from the route resource.
     *
     * This method accepts an array of HTTP methods and converts them to uppercase.
     * The converted methods are then stored in the `$excludedMethods` property.
     *
     * @param array $methods An array of HTTP methods to exclude.
     * @return self Returns the instance of the class for method chaining.
     */
    public function exceptMethods(array $methods): self
    {
        $this->excludedMethods = array_map(
            callback: 'strtoupper',
            array: $methods
        );
        return $this;
    }


    /**
     * Restrict the route to specific HTTP methods.
     *
     * This method accepts an array of HTTP methods and converts them to uppercase.
     * It then sets the allowed methods for the route.
     *
     * @param array $methods An array of HTTP methods (e.g., ['get', 'post']).
     * @return self Returns the current instance for method chaining.
     */
    public function onlyMethods(array $methods): self
    {
        $this->methods = array_map(
            callback: 'strtoupper',
            array: $methods
        );
        return $this;
    }


    /**
     * Defines the API resource routes and allows excluding specific HTTP methods.
     *
     * @param array $methods Optional array of HTTP methods to exclude. If not provided,
     *                       defaults to excluding 'GET', 'POST', 'PUT', 'PATCH', and 'DELETE'.
     *                       Methods are automatically converted to uppercase.
     * @return self Returns the current instance for method chaining.
     */
    public function apiResource(array $methods = []): self
    {
        if (!empty($methods)) {
            $this->excludedMethods = array_map(
                callback: 'strtoupper',
                array: $methods
            );
            return $this;
        }

        $this->excludedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        return $this;
    }


    /**
     * Registers routes for the controller methods that are not excluded.
     *
     * This method iterates over the list of HTTP methods and registers a route
     * for each method that is not in the excluded methods list. The route is
     * added to the Router with the corresponding controller method.
     *
     * @return void
     */
    public function register(): void
    {
        foreach ($this->methods as $method) {
            if (!in_array($method, $this->excludedMethods)) {
                $controllerMethod = strtolower($method);
                $target = [$this->controller, $controllerMethod];
                $methodFunction = ucfirst(strtolower($method));
                if (method_exists(Router::class, $methodFunction)) {
                    Router::$methodFunction($this->url, $target);
                } else {
                    throw new RouterException("Method $method not found in Router class.");
                }
            }
        }
    }
}
