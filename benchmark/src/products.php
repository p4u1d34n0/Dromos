<?php

require '/app/benchmark/bootstrap.php';

use Dromos\Router;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Benchmark\SwooleServer;
use Benchmark\Controllers\ProductController;

ProductController::boot();

Router::Get('/health', function (Request $request, Response $response) {
    return $response->json(['status' => 'ok', 'service' => 'product-service']);
});

Router::Get('/products', [ProductController::class, 'index']);
Router::Get('/products/{id}', [ProductController::class, 'show']);
Router::Post('/products', [ProductController::class, 'store']);
Router::Put('/products/{id}', [ProductController::class, 'update']);
Router::Delete('/products/{id}', [ProductController::class, 'destroy']);

$router = new Router();
$server = new SwooleServer('0.0.0.0', 9503, $router);
$server->start();
