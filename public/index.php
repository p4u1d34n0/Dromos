<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Router;
use App\Controllers\ViewController;
use App\HTTP\Response;
use App\HTTP\Request;

Router::GET(url: "/home/{id}", target: function (Request $request, Response $response) {
    return $response->json(['message' => $request->get('id')]);
});

Router::GET(url: "/data/{id}/user/{user_id}", target: function (Request $request, Response $response) {

    $viewController = new ViewController();
    $string = $viewController->merge($request->get('id'), $request->get('user_id'));

    return $response->json([
        'message' => $string,
        'id' => $request->get('id')
    ]);
  
});

/* Router::PUT("/home/{id}", [ViewController::class, 'index']);
Router::POST("/home/{id}", [ViewController::class, 'index']);
Router::HEAD("/home/{id}", [ViewController::class, 'index']);
Router::PATCH("/home/{id}", [ViewController::class, 'index']);
Router::DELETE("/home/{id}", [ViewController::class, 'index']);
Router::OPTIONS("/home/{id}", [ViewController::class, 'index']); */

// Run the router
Router::matchRoute();