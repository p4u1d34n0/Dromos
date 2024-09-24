
# Summary of How It Works

This section provides an overview of the functionality and operation of the Router project. It explains the core concepts, architecture, and workflow to help users understand how the system operates.

**Using Closure Functions**
```
Router::GET(url: "/home/{id}", target: function (Request $request, Response $response) {
    echo "The ID id ". $response->get('id');
});
```

**Using Controllers**
```
Router::GET("/home/{id}",       [SomeController::class, 'getMethodHandler']);
Router::PUT("/home/{id}",       [SomeController::class, 'putMethodHandler']);
Router::POST("/home/{id}",      [SomeController::class, 'postMethodHandler']);
Router::HEAD("/home/{id}",      [SomeController::class, 'headMethodHandler']);
Router::PATCH("/home/{id}",     [SomeController::class, 'patchMethodHandler']);
Router::DELETE("/home/{id}",    [SomeController::class, 'deleteMethodHandler']);
Router::OPTIONS("/home/{id}",   [SomeController::class, 'optionsMethodHandler']);
```

**Returning JSON**
```
Router::GET(url: "/home/{id}", target: function (Request $request, Response $response) {
    return $response->json(['message' => $request->get('id')]);
});

```

**Route Definition:**

You define a route with placeholders using curly braces (e.g., /data/{id}/user/{user_id}).

### Work In Progress

The idea here is for me to produce a lightweight PHP router for composer inclusion.
