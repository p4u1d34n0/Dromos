<?php

namespace App;

use App\Middleware\RequestParameters;
use App\RouterException;
use Closure;
use App\HTTP\Response;
use App\HTTP\Request;
use App\RouteResource;

class Router
{
    protected static $routes = [];

    public static function routes(): array
    {
        return self::$routes;
    }

    public static function Get(string $url, $target): string
    {
        return self::addRoute('GET', $url, $target);
    }

    public static function Post(string $url, $target): string
    {
        return self::addRoute('POST', $url, $target);
    }

    public static function Put(string $url, $target): string
    {
        return self::addRoute('PUT', $url, $target);
    }

    public static function Patch(string $url, $target): string
    {
        return self::addRoute('PATCH', $url, $target);
    }

    public static function Head(string $url, $target): string
    {
        return self::addRoute('HEAD', $url, $target);
    }

    public static function Delete(string $url, $target): string
    {
        return self::addRoute('DELETE', $url, $target);
    }

    public static function Options(string $url, $target): string
    {
        return self::addRoute('OPTIONS', $url, $target);
    }

    public static function Resource(string $url, string $controller): RouteResource
    {
        $resource = new RouteResource($url, $controller);
        $resource->register();
        return $resource;
    }

    public static function prefix(string $prefix)
    {
        // example: /api/v1
        
    }

    public static function matchRoute(): void
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $url = $_SERVER['REQUEST_URI'];

            if (isset(self::$routes[$method])) {

                foreach (self::$routes[$method] as $routeUrl => $target) {

                    if ($url === $routeUrl) {
                        self::handleTarget($target, []);
                        return;
                    }

                    $parameterExtraction = RequestParameters::handle($routeUrl, $url);

                    if (!empty($parameterExtraction)) {
                        $expectedUrl = self::reconstructUrl($routeUrl, $parameterExtraction);

                        if ($expectedUrl === $url) {
                            self::handleTarget($target, $parameterExtraction);
                            return;
                        }
                    }
                }

                RouterException::RouteNotFound();

            } else {
                RouterException::MethodNotAllowed();
            }
        } catch (\Throwable $e) {
            RouterExceptionHandler::handle($e);
        }
    }
    
    public static function addRoute(string $method, string $url, $target): string
    {
        self::$routes[$method][$url] = self::checkTarget($target);
        return self::class;
    }

    private static function checkTarget($target): array|Closure
    {
        if ($target instanceof Closure) {
            return $target;
        }

        if (is_array($target) && count($target) === 2 && is_string($target[0])) {
            $controllerInstance = new $target[0];

            if (!method_exists($controllerInstance, $target[1])) {
                RouterException::TargetNotFound();
            }

            $target[0] = $controllerInstance;
            return $target;
        }

        RouterException::TargetNotFound();
        return []; // Ensure a return value
    }

    private static function reconstructUrl(string $routeUrl, array $parameters): string
    {
        foreach ($parameters as $key => $value) {
            $routeUrl = str_replace("{{$key}}", $value, $routeUrl);
        }
        return $routeUrl;
    }

    private static function handleTarget($target, array $parameters): void
    {
        // Create Request and Response objects
        $response = new Response();
        $request = new Request($parameters);

        // Prepare the arguments to be passed
        $args = [];

        // Use reflection to determine what parameters are expected by the target
        $reflection = null;

        if ($target instanceof Closure) {
            $reflection = new \ReflectionFunction($target);
        } elseif (is_array($target) && count($target) === 2) {
            $reflection = new \ReflectionMethod($target[0], $target[1]);
        }

        if ($reflection) {
            foreach ($reflection->getParameters() as $param) {
                $paramType = $param->getType() ? $param->getType()->getName() : null;

                // Check if the parameter is a Request, Response, or a regular parameter (like $id)
                if ($paramType === Request::class) {
                    $args[] = $request;
                } elseif ($paramType === Response::class) {
                    $args[] = $response;
                } elseif (array_key_exists($param->getName(), $parameters)) {
                    $args[] = $parameters[$param->getName()];
                } elseif ($param->isOptional()) {
                    $args[] = $param->getDefaultValue();
                } else {
                    throw new \InvalidArgumentException("Unable to resolve parameter: " . $param->getName());
                }
            }
        }

        // Call the target with the resolved arguments
        if ($target instanceof Closure) {
            call_user_func_array($target, $args);
        } elseif (is_array($target) && count($target) === 2) {
            call_user_func_array([$target[0], $target[1]], $args);
        }
    }

}
