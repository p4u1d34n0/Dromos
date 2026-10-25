<?php

require '/app/benchmark/bootstrap.php';

use Dromos\Router;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Benchmark\SwooleServer;
use Benchmark\Controllers\UserController;

UserController::boot();

Router::Get('/health', function (Request $request, Response $response) {
    return $response->json(['status' => 'ok', 'service' => 'user-service']);
});

Router::Get('/users', [UserController::class, 'index']);
Router::Get('/users/{id}', [UserController::class, 'show']);
Router::Post('/users', [UserController::class, 'store']);
Router::Put('/users/{id}', [UserController::class, 'update']);
Router::Delete('/users/{id}', [UserController::class, 'destroy']);

$router = new Router();
$server = new SwooleServer('0.0.0.0', 9502, $router);
$server->start();
