<img src="http://dromos.pauldean.me/Dromos.png" alt="Dromos - Superlight PHP Router" height="300px" style="border-radius:30px">

# Why Choose Dromos?

> Dromos is a lightweight, PSR-7 and PSR-15 compliant PHP micro-service library designed for maximum flexibility and performance. It offers fast routing, route caching, middleware support, and a minimal emitter layer to send PSR-7 ResponseInterface objects to any PHP SAPI or server environment.

## Key Features

* PSR-7 / PSR-15 Compliance: Native HTTP message and middleware implementations—no external PSR-7 libraries required.
* Fast Routing: Define expressive routes with parameters, wildcards, and HTTP method support.
* Route Caching: Serialize and load route trees to eliminate route parsing overhead in production.
* Minimal Emitter Layer: A built-in SapiEmitter handles status line, headers, and body output. Implement EmitterInterface to target other runtimes like OpenSwoole.
* Micro-Service Ready: Perfect for REST, RPC, or event-driven micro-services with zero framework magic.

Frameworks like Laravel and Symfony excel at monolithic apps but introduce significant overhead:

- Lean Footprint: Core library <100 KB.
- High Concurrency: Custom emitters + OpenSwoole handle 10k+ req/s.
- Zero War Story: No imposed folder structure—organize your code your way.

Implement OpenSwoole and you can start building micro-services that can rival Node.js or Go in performance, with PHP’s robust ecosystem. :shipit:


Install via Composer:
```
composer require p4u1d34n0/dromos
```


# Summary of How It Works

This section provides an overview of the functionality and operation of the Router project. It explains the core concepts, architecture, and workflow to help users understand how the system operates.

## Defined a Resource Group

By default, the following public functions are required to exist in the specified controller:

- `get`
- `post`
- `put`
- `patch`
- `delete`
- `options`
- `head`

```php
Router::Resource(
    url: "/home/{parameter}", 
    controller: ComeController::class
); 
```

### Customizing Methods

You can define which methods to use with the resource group:

- **API Resource (Defaults to: get, post, put, patch, delete)**
  ```php
  ->apiResource()
  ```

- **Exclude Methods**
  ```php
  ->exceptMethods(["HEAD", "OPTIONS", "DELETE"])
  // Will look for: get, put, post, patch
  ```

- **Include Only Specific Methods**
  ```php
  ->onlyMethods(["GET", "POST"])
  // Will only require: get, post
  ```

## Using Closure Functions

```php
Router::GET(url: "/home/{id}", target: function (Request $request, Response $response) {
    echo "The ID is " . $response->get('id');
});
```

## Using Controllers

```php
Router::GET("/home/{id}",       [SomeController::class, 'getMethodHandler']);
Router::PUT("/home/{id}",       [SomeController::class, 'putMethodHandler']);
Router::POST("/home/{id}",      [SomeController::class, 'postMethodHandler']);
Router::HEAD("/home/{id}",      [SomeController::class, 'headMethodHandler']);
Router::PATCH("/home/{id}",     [SomeController::class, 'patchMethodHandler']);
Router::DELETE("/home/{id}",    [SomeController::class, 'deleteMethodHandler']);
Router::OPTIONS("/home/{id}",   [SomeController::class, 'optionsMethodHandler']);
```

## Returning JSON

```php
Router::GET(url: "/home/{id}", target: function (Request $request, Response $response) {
    return $response->json(['message' => $request->get('id')]);
});
```

## Route Definition

You define a route with placeholders using curly braces (e.g., `/data/{id}/user/{user_id}`).

### Work In Progress

The idea here is to produce a lightweight PHP router for Composer inclusion.
