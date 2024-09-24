<?php

namespace App;

use App\RouterExceptionHandler;

class RouterException extends RouterExceptionHandler
{
    public static function MethodNotAllowed($available = []): void
    {
        $message = 'Method not allowed' . ($available ? '. Available methods: ' . implode(', ', $available) : '');
        self::handle(
            e: new RouterExceptionHandler(message: $message), 
            responseCode: 405
        );
    }

    public static function RouteNotFound(): void
    {
        self::handle(
            e: new RouterExceptionHandler(message: 'Route not found'), 
            responseCode: 404
        );
    }

    public static function TargetNotFound(array $missing = null): void
    {

        $missingRoutes = array_map(callback: function ($route): array {
            return [$route[0]::class => $route[1] ];
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