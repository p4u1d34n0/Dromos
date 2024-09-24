<?php

namespace App;

use App\Middleware\RequestParameters;
use App\RouterExceptionHandler;
use Closure;
use App\HTTP\Response;
use App\HTTP\Request;

class Router
{
    protected static $routes = [];

    public static function GET(string $url, $target): string
    {
        return self::addRoute('GET', $url, $target);
    }

    public static function POST(string $url, $target): string
    {
        return self::addRoute('POST', $url, $target);
    }

    public static function PUT(string $url, $target): string
    {
        return self::addRoute('PUT', $url, $target);
    }

    public static function PATCH(string $url, $target): string
    {
        return self::addRoute('PATCH', $url, $target);
    }

    public static function HEAD(string $url, $target): string
    {
        return self::addRoute('HEAD', $url, $target);
    }

    public static function DELETE(string $url, $target): string
    {
        return self::addRoute('DELETE', $url, $target);
    }

    public static function OPTIONS(string $url, $target): string
    {
        return self::addRoute('OPTIONS', $url, $target);
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

                RouterExceptionHandler::RouteNotFound();

            } else {
                RouterExceptionHandler::MethodNotAllowed();
            }
        } catch (\Throwable $e) {
            RouterExceptionHandler::handle($e);
        }
    }
    
    private static function addRoute(string $method, string $url, $target): string
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
                throw new \InvalidArgumentException('Invalid route target: Method does not exist');
            }

            $target[0] = $controllerInstance;
            return $target;
        }

        throw new \InvalidArgumentException('Invalid route target');
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

        $response = new Response();
        $request = new Request($parameters);

        /* if ($target instanceof Closure) {
            call_user_func_array($target, array_values($parameters));
        } elseif (is_array($target) && count($target) === 2) {
            call_user_func_array([$target[0], $target[1]], array_values($parameters));
        } */

        if ($target instanceof Closure) {
            call_user_func_array($target, [$request, $response]);
        } elseif (is_array($target) && count($target) === 2) {
            call_user_func_array([$target[0], $target[1]], [$request, $response]);
        }

        /* if ($target instanceof Closure) {
            call_user_func_array($target, array_merge([$response], array_values($parameters)));
        } elseif (is_array($target) && count($target) === 2) {
            call_user_func_array([$target[0], $target[1]], array_merge([$response], array_values($parameters)));
        } */
    }
}
