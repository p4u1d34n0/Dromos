<img src="http://dromos.pauldean.me/Dromos.png" alt="Dromos - Superlight PHP Router" height="300px">

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
