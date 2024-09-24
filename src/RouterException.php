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

    public static function TargetNotFound(): void
    {
        self::handle(
            e: new RouterExceptionHandler(message: 'Route Target not found'), 
            responseCode: 404
        );
    }
}