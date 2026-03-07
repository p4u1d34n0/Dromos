<?php

require '/app/benchmark/bootstrap.php';

use Dromos\Router;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Middleware\CorsMiddleware;
use Dromos\Middleware\AuthMiddleware;
use Dromos\Middleware\RateLimitMiddleware;
use Benchmark\SwooleServer;
use Benchmark\GatewayProxy;

// Health check — no auth required
Router::Get('/health', function (Request $request, Response $response) {
    return $response->json([
        'status' => 'ok',
        'service' => 'gateway',
        'timestamp' => time(),
    ]);
});

// API routes with auth
Router::group('/api', function ($api) {
    $api->middleware(new AuthMiddleware(function (string $token) {
        return $token === 'benchmark-token' ? ['id' => 1, 'role' => 'admin'] : false;
    }));

    // User service proxy
    $api->get('/users', function (Request $request, Response $response) {
        return GatewayProxy::forward($request, 'user-service', 9502, '/users');
    });
    $api->get('/users/{id}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id');
        return GatewayProxy::forward($request, 'user-service', 9502, "/users/{$id}");
    });
    $api->post('/users', function (Request $request, Response $response) {
        return GatewayProxy::forward($request, 'user-service', 9502, '/users');
    });
    $api->put('/users/{id}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id');
        return GatewayProxy::forward($request, 'user-service', 9502, "/users/{$id}");
    });
    $api->delete('/users/{id}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id');
        return GatewayProxy::forward($request, 'user-service', 9502, "/users/{$id}");
    });

    // Product service proxy
    $api->get('/products', function (Request $request, Response $response) {
        return GatewayProxy::forward($request, 'product-service', 9503, '/products');
    });
    $api->get('/products/{id}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id');
        return GatewayProxy::forward($request, 'product-service', 9503, "/products/{$id}");
    });
    $api->post('/products', function (Request $request, Response $response) {
        return GatewayProxy::forward($request, 'product-service', 9503, '/products');
    });
    $api->put('/products/{id}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id');
        return GatewayProxy::forward($request, 'product-service', 9503, "/products/{$id}");
    });
    $api->delete('/products/{id}', function (Request $request, Response $response) {
        $id = $request->getAttribute('id');
        return GatewayProxy::forward($request, 'product-service', 9503, "/products/{$id}");
    });
});

// Boot server with global middleware
$router = new Router();
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new RateLimitMiddleware(1000000, 60));

$server = new SwooleServer('0.0.0.0', 9501, $router);
$server->start();
