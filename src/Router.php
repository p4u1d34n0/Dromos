<?php

namespace Dromos;

use Dromos\Middleware\RequestParameters;
use Dromos\RouterException;
use Closure;
use Dromos\HTTP\Response;
use Dromos\HTTP\Request;
use Dromos\RouteResource;

/**
 * Class Router
 *
 * This class is responsible for handling the routing of HTTP requests.
 * It maps URLs to specific controller actions and manages the dispatching
 * of requests to the appropriate handlers.
 *
 * @package Router
 */
class Router
{
    // Array to store registered routes
    protected static $routes = [];

    // Array to store missing routes
    private static $missingRoutes = [];

    /**
     * Checks the registered routes and returns them.
     *
     * This method retrieves the list of registered routes. If there are any missing routes,
     * it triggers a RouterException indicating that the target was not found.
     *
     * @return array The list of registered routes.
     * @throws RouterException If there are missing routes.
     */
    public static function checkRoutes(): array
    {
        $routes = self::$routes;
        if (!empty(self::$missingRoutes)) {
            RouterException::TargetNotFound(missing: self::$missingRoutes);
        }
        return $routes;
    }

    /**
     * Registers a new GET route with the specified URL and target.
     *
     * @param string $url The URL pattern for the GET route.
     * @param mixed $target The target handler for the GET route. This can be a callback function or a controller method.
     * @return string The result of adding the route.
     */
    public static function Get(string $url, $target): string
    {
        return self::addRoute(
            method: 'GET',
            url: $url,
            target: $target
        );
    }

    /**
     * Registers a new POST route with the given URL and target.
     *
     * @param string $url The URL pattern for the route.
     * @param mixed $target The target handler for the route, which can be a callback or a controller action.
     * @return string The result of adding the route.
     */
    public static function Post(string $url, $target): string
    {
        return self::addRoute(
            method: 'POST',
            url: $url,
            target: $target
        );
    }

    /**
     * Registers a new PUT route with the given URL and target.
     *
     * @param string $url The URL pattern for the route.
     * @param mixed $target The target handler for the route, which can be a callback or a controller action.
     * @return string The result of adding the route.
     */
    public static function Put(string $url, $target): string
    {
        return self::addRoute(
            method: 'PUT',
            url: $url,
            target: $target
        );
    }

    /**
     * Registers a new PATCH route with the given URL and target.
     *
     * @param string $url The URL pattern for the route.
     * @param mixed $target The target handler for the route, which can be a callback or a controller action.
     * @return string The result of adding the route.
     */
    public static function Head(string $url, $target): string
    {
        return self::addRoute(
            method: 'HEAD',
            url: $url,
            target: $target
        );
    }

    /**
     * Registers a DELETE route with the specified URL and target.
     *
     * @param string $url The URL pattern for the DELETE route.
     * @param mixed $target The target handler for the route, which can be a callable or a controller action.
     * @return string The result of adding the route, typically a confirmation message or route identifier.
     */
    public static function Options(string $url, $target): string
    {
        return self::addRoute(
            method: 'OPTIONS',
            url: $url,
            target: $target
        );
    }

    /**
     * Registers a new resource route.
     *
     * This method creates a new instance of the RouteResource class with the provided URL and controller,
     * registers the resource, and returns the instance.
     *
     * @param string $url The URL pattern for the resource.
     * @param string $controller The controller associated with the resource.
     * @return RouteResource The registered resource route.
     */
    public static function Resource(string $url, string $controller): RouteResource
    {
        $resource = new RouteResource(url: $url, controller: $controller);
        $resource->register();
        return $resource;
    }

    /**
     * Matches the current request to a registered route and dispatches it.
     *
     * This method compares the current request URL and method to the registered routes
     * and dispatches the request to the appropriate handler. If no matching route is found,
     * it triggers a RouterException indicating that the route was not found.
     *
     * @return void
     */
    public static function matchRoute(): void
    {
        try {

            $request = new Request();
            $response = new Response();

            $method = $request->getMethod();
            $url = $request->getUri();

            if (isset(self::$routes[$method])) {

                foreach (self::$routes[$method] as $routeUrl => $target) {

                    if ($url === $routeUrl) {
                        self::handleTarget($target, parameters: [], request: $request, response: $response);
                        return;
                    }

                    $parameters = RequestParameters::handle(route: $routeUrl, url: $url);
                    if (!empty($parameters)) {
                        $expectedUrl = self::reconstructUrl($routeUrl, $parameters);
                        if ($expectedUrl === $url) {
                            self::handleTarget(target: $target, parameters: $parameters, request: $request, response: $response);
                            return;
                        }
                    }
                }

                RouterException::RouteNotFound();
            } else {
                RouterException::MethodNotAllowed();
            }
        } catch (\Throwable $e) {
            RouterExceptionHandler::handle(e: $e);
        }
    }

    /**
     * Adds a route to the router.
     *
     * @param string $method The HTTP method (e.g., 'GET', 'POST') for the route.
     * @param string $url The URL pattern for the route.
     * @param mixed $target The target handler for the route, which can be a callable or a controller action.
     * @return string The class name of the router.
     */
    public static function addRoute(string $method, string $url, $target): string
    {
        self::$routes[$method][$url] = self::checkTarget(target: $target);
        return self::class;
    }

    /**
     * Checks the target and returns it as an array or Closure.
     *
     * This method verifies if the provided target is a Closure or an array representing
     * a controller and its method. If the target is a Closure, it is returned as is.
     * If the target is an array with exactly two elements and the first element is a string,
     * it is assumed to be a controller class name and method name. The method will instantiate
     * the controller and check if the method exists. If the method does not exist, it adds the
     * controller and method to the missing routes list.
     *
     * @param mixed $target The target to check, which can be a Closure or an array with a controller class name and method name.
     * @return array|Closure The validated target, either as a Closure or an array with the instantiated controller and method name.
     */
    private static function checkTarget($target): array|Closure
    {
        if ($target instanceof Closure) {
            return $target;
        }

        if (is_array(value: $target) && count(value: $target) === 2 && is_string(value: $target[0])) {
            $controllerInstance = new $target[0];

            if (!method_exists(object_or_class: $controllerInstance, method: $target[1])) {
                self::$missingRoutes[] = [$controllerInstance, $target[1]];
            }

            $target[0] = $controllerInstance;
            return $target;
        }

        return []; // Ensure a return value
    }

    /**
     * Reconstructs a URL by replacing placeholders with provided parameters.
     *
     * This method takes a route URL containing placeholders in the format `{key}`
     * and replaces them with corresponding values from the provided parameters array.
     *
     * @param string $routeUrl The route URL containing placeholders.
     * @param array $parameters An associative array where keys correspond to placeholders in the route URL and values are the replacements.
     * @return string The reconstructed URL with placeholders replaced by parameter values.
     */
    private static function reconstructUrl(string $routeUrl, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $routeUrl = str_replace(search: "{{$key}}", replace: $value, subject: $routeUrl);
        }
        return $routeUrl;
    }

    /**
     * Handles the invocation of a target callable with the provided parameters.
     *
     * This method uses reflection to determine the expected parameters of the target
     * callable and resolves them accordingly. It supports both closures and class methods.
     *
     * @param callable $target The target callable to be invoked. It can be a closure or an array
     *                         representing a class method (e.g., [ClassName::class, 'methodName']).
     * @param array $parameters An associative array of parameters to be passed to the target callable.
     *
     * @throws \InvalidArgumentException If a required parameter cannot be resolved.
     *
     * @return void
     */
    protected static function handleTarget($target, array $parameters, Request $request, Response $response): void
    {

        $response = new Response();
        $request = new Request();

        // Set the route parameters into the request
        $request->setRouteParams($parameters);

        $args = [];
        $reflection = null;

        // Reflection to match method signatures
        if ($target instanceof Closure) {
            $reflection = new \ReflectionFunction(function: $target);
        } elseif (is_array(value: $target) && count(value: $target) === 2) {
            $reflection = new \ReflectionMethod(objectOrMethod: $target[0], method: $target[1]);
        }

        if ($reflection) {
            foreach ($reflection->getParameters() as $param) {
                $paramType = $param->getType() ? $param->getType()->getName() : null;

                if ($paramType === Request::class) {
                    $args[] = $request;
                } elseif ($paramType === Response::class) {
                    $args[] = $response;
                } elseif (array_key_exists(key: $param->getName(), array: $parameters)) {
                    $args[] = $parameters[$param->getName()];
                } elseif ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException(message: "Unable to resolve parameter: " . $param->getName());
                }
            }
        }

        // Invoke the target (either Closure or Controller)
        if ($target instanceof Closure) {
            call_user_func_array(callback: $target, args: $args);
        } elseif (is_array(value: $target) && count(value: $target) === 2) {
            call_user_func_array(callback: [$target[0], $target[1]], args: $args);
        }
    }
}
