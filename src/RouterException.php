<?php

namespace App;

use App\RouterExceptionHandler;

/**
 * Class RouterException
 *
 * This class extends the RouterExceptionHandler and provides methods to handle
 * various routing exceptions such as "Method Not Allowed", "Route Not Found",
 * and "Target Not Found". Each method generates an appropriate exception message
 * and delegates the handling to the RouterExceptionHandler with the corresponding
 * HTTP response code.
 *
 * Methods:
 * - MethodNotAllowed(array $available = []): void
 *   Handles the "Method Not Allowed" exception by generating a message indicating
 *   the requested HTTP method is not allowed and includes available methods if provided.
 *   Delegates the handling to RouterExceptionHandler with a 405 response code.
 *
 * - RouteNotFound(): void
 *   Handles the scenario where a route is not found by triggering the RouterExceptionHandler
 *   with a message indicating the route was not found and sets the HTTP response code to 404.
 *
 * - TargetNotFound(array $missing = null): void
 *   Handles the case when a target route is not found by processing an array of missing routes
 *   and triggers the appropriate exception handling mechanism with a 404 response code.
 *   If the target route is not found, this method will throw a RouterExceptionHandler with a 404 response code.
 */
class RouterException extends RouterExceptionHandler
{

    /**
     * Handles the "Method Not Allowed" exception.
     *
     * This method generates an exception message indicating that the requested
     * HTTP method is not allowed. If available methods are provided, they will
     * be included in the message. The method then delegates the handling of the
     * exception to the `RouterExceptionHandler` with a 405 response code.
     *
     * @param array $available An array of available HTTP methods.
     *
     * @return void
     */
    public static function MethodNotAllowed($available = []): void
    {
        $message = 'Method not allowed' . ($available ? '. Available methods: ' . implode(', ', $available) : '');
        self::handle(
            e: new RouterExceptionHandler(message: $message),
            responseCode: 405
        );
    }

    /**
     * Handles the scenario where a route is not found.
     *
     * This method triggers the RouterExceptionHandler with a message indicating
     * that the route was not found and sets the HTTP response code to 404.
     *
     * @return void
     */
    public static function RouteNotFound(): void
    {
        self::handle(
            e: new RouterExceptionHandler(message: 'Route not found'),
            responseCode: 404
        );
    }

    /**
     * Handles the case when a target route is not found.
     *
     * This method processes an array of missing routes and triggers the appropriate
     * exception handling mechanism with a 404 response code.
     *
     * @param array|null $missing An array of missing routes, where each route is represented
     *                            as an array with the class and method that could not be found.
     *
     * @return void
     *
     * @throws RouterExceptionHandler If the target route is not found, this method will throw
     *                                a RouterExceptionHandler with a 404 response code.
     */
    public static function TargetNotFound(array $missing = null): void
    {

        $missingRoutes = array_map(callback: function ($route): array {
            return [$route[0]::class => $route[1]];
        }, array: $missing);

        if (!empty($missingRoutes)) {
            self::handle(
                e: new RouterExceptionHandler(message: 'Missing Target Methods'),
                responseCode: 404,
                args: ['Missing Routes' => $missingRoutes]
            );
            exit;
        }

        self::handle(
            e: new RouterExceptionHandler(message: 'Target not found'),
            responseCode: 404,
            args: $missingRoutes
        );
    }
}
