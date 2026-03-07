<img src="http://dromos.pauldean.me/Dromos.png" alt="Dromos - Superlight PHP Router" height="300px" style="border-radius:30px">

# Why Choose Dromos?

> Dromos is a lightweight PHP micro-service library with PSR-7 and PSR-15 inspired interfaces, designed for maximum flexibility and performance. It offers fast routing, route caching, middleware support, and a minimal emitter layer to send responses to any PHP SAPI or server environment.

## Key Features

* **PSR-7 / PSR-15 Inspired** — Native HTTP message and middleware implementations with PSR-compatible APIs. No external libraries required.
* **Fast Routing** — Expressive routes with parameters, wildcards, route groups, and HTTP method support.
* **Route Groups** — Group routes under a shared prefix with per-group middleware.
* **Route Caching** — Serialize and load route trees to eliminate route parsing overhead in production.
* **Middleware Pipeline** — Global and per-route middleware with PSR-15 style handler chains. Ships with CORS, Auth, and Rate Limiting middleware.
* **Input Validation** — Built-in validator with pipe-delimited rules for API input.
* **JSON-First API Design** — JSON request body parsing, JSON error responses, and response helpers.
* **Minimal Emitter Layer** — Built-in `Emitter` handles status line, headers, and body output. Implement `EmitterInterface` to target other runtimes like OpenSwoole.
* **Micro-Service Ready** — Perfect for REST, RPC, or event-driven micro-services with zero framework magic.

Frameworks like Laravel and Symfony excel at monolithic apps but introduce significant overhead:

- **Lean Footprint** — Core library < 100 KB.
- **High Concurrency** — Custom emitters + OpenSwoole handle 10k+ req/s.
- **Zero War Story** — No imposed folder structure. Organise your code your way.

Implement OpenSwoole and you can start building micro-services that can rival Node.js or Go in performance, with PHP's robust ecosystem.

## Installation

```
composer require pauldeano/dromos
```

Requires **PHP 8.2+** with zero external dependencies.

---

# Routing

## Basic Routes

```php
use Dromos\Router;
use Dromos\Http\Request;
use Dromos\Http\Response;

Router::Get("/users", function (Request $request, Response $response) {
    return $response->json(['users' => []]);
});

Router::Post("/users", function (Request $request, Response $response) {
    $body = $request->getParsedBody();
    return $response->created(['id' => 1, 'name' => $body['name']]);
});
```

All HTTP methods are supported:

```php
Router::Get("/path",     $target);
Router::Post("/path",    $target);
Router::Put("/path",     $target);
Router::Patch("/path",   $target);
Router::Delete("/path",  $target);
Router::Head("/path",    $target);
Router::Options("/path", $target);
```

## Route Parameters

```php
Router::Get("/users/{id}", function (Request $request, Response $response) {
    $id = $request->getAttribute('id');
    return $response->json(['id' => $id]);
});
```

## Using Controllers

```php
Router::Get("/users",       [UserController::class, 'index']);
Router::Get("/users/{id}",  [UserController::class, 'show']);
Router::Post("/users",      [UserController::class, 'store']);
Router::Put("/users/{id}",  [UserController::class, 'update']);
Router::Patch("/users/{id}",[UserController::class, 'patch']);
Router::Delete("/users/{id}",[UserController::class, 'destroy']);
```

## Resource Routes

Auto-register all HTTP methods for a controller:

```php
Router::Resource("/users/{id}", UserController::class);
```

By default, the controller must have public methods named `get`, `post`, `put`, `patch`, `delete`, `options`, `head`. Routes are registered when the `RouteResource` object goes out of scope, so chained methods are applied before registration.

### Customising Resource Methods

```php
// API resource (GET, POST, PUT, PATCH, DELETE only — excludes OPTIONS and HEAD)
Router::Resource("/users/{id}", UserController::class)->apiResource();

// Exclude specific methods
Router::Resource("/users/{id}", UserController::class)
    ->exceptMethods(["HEAD", "OPTIONS", "DELETE"]);

// Only specific methods
Router::Resource("/users/{id}", UserController::class)
    ->onlyMethods(["GET", "POST"]);
```

## Route Groups

Group routes under a shared prefix with optional per-group middleware:

```php
use Dromos\Middleware\AuthMiddleware;

Router::group('/api/v1', function ($group) {
    // Public routes
    $group->get('/status', function (Request $request, Response $response) {
        return $response->json(['status' => 'ok']);
    });

    // Protected routes with auth middleware — define middleware BEFORE nested groups
    $group->group('/users', function ($users) {
        $users->middleware(new AuthMiddleware(function ($token) {
            return $token === 'valid-token' ? ['user_id' => 1] : false;
        }));

        $users->get('/list', [UserController::class, 'index']);
        $users->post('/create', [UserController::class, 'store']);
        $users->get('/{id}', [UserController::class, 'show']);
        $users->put('/{id}', [UserController::class, 'update']);
        $users->delete('/{id}', [UserController::class, 'destroy']);
    });
});
```

Nested groups inherit the parent's prefix and middleware stack at the point of creation. Add middleware to a group **before** defining nested groups to ensure inheritance.

---

# Middleware

Dromos uses PSR-15 style middleware. Middleware can be applied globally or per-route/group.

## Global Middleware

```php
$router = new Router();
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new RateLimitMiddleware(100, 60));
```

## Per-Route / Per-Group Middleware

See [Route Groups](#route-groups) above for per-group middleware.

## Built-in Middleware

### CORS Middleware

```php
use Dromos\Middleware\CorsMiddleware;

$cors = new CorsMiddleware([
    'allowed_origins'   => ['https://example.com', 'https://app.example.com'],
    'allowed_methods'   => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers'   => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'max_age'           => 86400,
    'allow_credentials' => true,
]);

$router->addMiddleware($cors);
```

All config keys are optional. Defaults to `allowed_origins: ['*']`.

Handles OPTIONS preflight requests automatically with a 204 response. Returns 403 for disallowed origins on preflight.

**Note:** `allow_credentials: true` cannot be used with wildcard `allowed_origins: ['*']`. Specify explicit origins when using credentials. An `InvalidArgumentException` is thrown if this combination is detected.

### Auth Middleware

Supports Bearer tokens and API keys:

```php
use Dromos\Middleware\AuthMiddleware;

$auth = new AuthMiddleware(function (string $token) {
    // Your authentication logic here.
    // Return a truthy value (user array/object) on success, or false/null on failure.
    // IMPORTANT: For API key comparison, use hash_equals() to prevent timing attacks.
    $user = MyUserService::validateToken($token);
    return $user ?: false;
});
```

- Extracts `Bearer <token>` from the `Authorization` header
- Falls back to the `X-API-Key` header
- On success, stores the result as `$request->getAttribute('auth_user')`
- On failure, returns a 401 JSON response

### Rate Limit Middleware

```php
use Dromos\Middleware\RateLimitMiddleware;

// 100 requests per 60-second window
$rateLimiter = new RateLimitMiddleware(100, 60);

$router->addMiddleware($rateLimiter);
```

Adds `X-RateLimit-Limit`, `X-RateLimit-Remaining`, and `X-RateLimit-Reset` headers to all responses. Returns 429 with `Retry-After` header when exceeded.

**Important:** Uses in-memory per-IP tracking. This is only effective in long-running processes (OpenSwoole, ReactPHP). In standard PHP-FPM deployments, each request runs in isolated memory and counters are never shared. For FPM, implement your own middleware backed by Redis or similar.

### Custom Middleware

Implement `MiddlewareInterface`:

```php
use Dromos\Http\Middleware\MiddlewareInterface;
use Dromos\Http\Middleware\RequestHandlerInterface;
use Dromos\Http\Message\ServerRequestInterface;
use Dromos\Http\Message\ResponseInterface;

class MyMiddleware implements MiddlewareInterface
{
    public function handle(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Before the route handler
        $request = $request->withAttribute('started_at', microtime(true));

        // Call the next handler
        $response = $handler->handle($request);

        // After the route handler
        return $response->withHeader('X-Response-Time', '42ms');
    }
}
```

---

# Request & Response

## JSON Request Body Parsing

JSON request bodies (`Content-Type: application/json`) are automatically parsed and available via `getParsedBody()`. Request bodies are limited to 1MB by default with a max JSON nesting depth of 64.

```php
Router::Post("/users", function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    // $data is the decoded JSON array
    return $response->created(['id' => 1, 'name' => $data['name']]);
});
```

## Response Helpers

All response helpers return a new immutable Response instance:

```php
// JSON response
$response->json(['key' => 'value'], 200);

// Plain text
$response->text('Hello World', 200);

// HTML (writes raw unescaped content — sanitise user input before passing)
$response->html('<h1>Hello</h1>', 200);

// 201 Created with JSON body
$response->created(['id' => 1]);

// 204 No Content
$response->noContent();

// Redirect (URL is sanitised against header injection)
$response->redirect('/new-location', 302);
```

## Error Responses

Routing errors return structured JSON. For `RouterException` errors (404, 405), the exception message is included. For unexpected errors, a generic "Internal Server Error" message is returned to prevent leaking internal details.

```json
{
    "error": "Not Found",
    "message": "Route not found: /unknown",
    "status": 404
}
```

---

# Input Validation

```php
use Dromos\Validation\Validator;

Router::Post("/users", function (Request $request, Response $response) {
    $validator = new Validator($request->getParsedBody(), [
        'name'  => 'required|string|min:2|max:100',
        'email' => 'required|email',
        'age'   => 'integer|min:0|max:150',
        'role'  => 'in:admin,user,editor',
    ]);

    if ($validator->fails()) {
        return $response->json(['errors' => $validator->errors()], 422);
    }

    $clean = $validator->validated(); // Only validated fields, unknown keys stripped
    return $response->created($clean);
});
```

**Note:** `validated()` throws `ValidationException` if validation has not passed. Always check `fails()` first, or wrap in a try/catch. `validated()` returns the original values filtered by key — it does not sanitise or type-cast data.

Unknown rule names throw `InvalidArgumentException` to catch typos early.

### Available Rules

| Rule | Description |
|------|-------------|
| `required` | Field must be present and non-empty |
| `string` | Must be a string |
| `integer` | Must be an integer |
| `numeric` | Must be numeric |
| `email` | Must be a valid email address |
| `url` | Must be a valid URL |
| `boolean` | Must be a boolean value |
| `array` | Must be an array |
| `min:n` | Minimum length (string), value (numeric), or count (array) |
| `max:n` | Maximum length (string), value (numeric), or count (array) |
| `in:a,b,c` | Must be one of the listed values |
| `regex:/pattern/` | Must match the regex pattern (pipe characters inside regex are handled correctly) |

Rules are pipe-delimited: `'required|string|min:2|max:100'`

---

# Emitter

Send a response to the client:

```php
use Dromos\Router;
use Dromos\Http\Request;
use Dromos\Http\Emitter\Emitter;

$router = new Router();
$response = $router->handle(new Request());

$emitter = new Emitter();
$emitter->emit($response);
```

### Custom Emitters

Implement `EmitterInterface` to target non-SAPI environments (e.g., OpenSwoole):

```php
use Dromos\Http\Emitter\EmitterInterface;
use Dromos\Http\Message\ResponseInterface;

class SwooleEmitter implements EmitterInterface
{
    public function emit(ResponseInterface $response): void
    {
        // Your OpenSwoole response logic here
    }
}
```

---

# Environment Variables

```php
use Dromos\Env\EnvLoader;

EnvLoader::load(__DIR__ . '/.env');

$dbHost = EnvLoader::get('DB_HOST', 'localhost');
```

Loads `.env` files into `$_ENV` and `putenv()`. Skips comments (`#`) and malformed lines. Falsy values like `"0"` are correctly preserved.

---

# Route Caching

For production, cache compiled routes to skip parsing:

```env
ROUTER_CACHE_FILE=/tmp/dromos_routes.cache
```

```php
Router::initialize(); // Loads cache if ROUTER_CACHE_FILE is set
```

Or manage manually:

```php
Router::enableCache('/tmp/routes.cache');
Router::clearCache();
Router::disableCache();
```

**Note:** Route caching uses `json_encode()`. Routes using Closure handlers cannot be cached — use controller targets for cacheable routes.

---

# Full Example

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Dromos\Router;
use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Http\Emitter\Emitter;
use Dromos\Env\EnvLoader;
use Dromos\Middleware\CorsMiddleware;
use Dromos\Middleware\RateLimitMiddleware;
use Dromos\Middleware\AuthMiddleware;
use Dromos\Validation\Validator;

// Load environment
EnvLoader::load(__DIR__ . '/.env');

// Optional: enable route caching via ROUTER_CACHE_FILE env var
Router::initialize();

// Define routes
Router::group('/api', function ($api) {
    // Public
    $api->get('/health', function (Request $request, Response $response) {
        return $response->json(['status' => 'ok']);
    });

    // Protected
    $api->group('/v1', function ($v1) {
        $v1->middleware(new AuthMiddleware(function ($token) {
            return MyAuth::validate($token);
        }));

        $v1->get('/users', [UserController::class, 'index']);
        $v1->post('/users', [UserController::class, 'store']);
    });
});

// Boot
$router = new Router();
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new RateLimitMiddleware(100, 60));

$response = $router->handle(new Request());

(new Emitter())->emit($response);
```

---

# Testing

For unit testing, use `Router::reset()` between tests to clear all registered routes and middleware:

```php
Router::reset();
RateLimitMiddleware::resetStore();
```

### Creating Test Requests

Use `Request::create()` to build requests without depending on PHP superglobals:

```php
use Dromos\Http\Request;

$request = Request::create(
    method: 'POST',
    uri: '/api/users',
    headers: ['Content-Type' => 'application/json'],
    parsedBody: ['name' => 'Jane']
);
```

---

# License

MIT
