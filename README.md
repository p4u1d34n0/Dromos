<img src="http://dromos.pauldean.me/Dromos.png" alt="Dromos - Superlight PHP Router" height="300px" style="border-radius:30px">

# Why Choose Dromos?

> Dromos is a lightweight PHP micro-service library that implements PSR-7 and PSR-15, designed for maximum flexibility and performance. It offers fast routing, route caching, middleware support, and a minimal emitter layer to send responses to any PHP SAPI or server environment.

## Key Features

* **PSR-7 / PSR-15** — Requests, responses, streams and URIs implement the PSR-7 interfaces, and PSR-15 middleware from any package runs alongside Dromos middleware. The only dependencies are the PSR interface packages.
* **Fast Routing** — Expressive routes with parameters, route groups, and HTTP method support. Routes compile into a hash map plus regexes grouped by path prefix, so lookups take the same time with 20 routes or 2,000.
* **Route Groups** — Group routes under a shared prefix with per-group middleware.
* **Route Caching** — Store the compiled route table in an opcache-friendly PHP file that refreshes itself when routes change.
* **Middleware Pipeline** — Global and per-route middleware with PSR-15 style handler chains. Ships with CORS, Auth, and Rate Limiting middleware.
* **Input Validation** — Built-in validator with pipe-delimited rules for API input.
* **JSON-First API Design** — JSON request body parsing, JSON error responses, and response helpers.
* **Minimal Emitter Layer** — `Emitter` sends responses under PHP-FPM, and `SwooleEmitter` under OpenSwoole. Implement `EmitterInterface` to target other runtimes.
* **Micro-Service Ready** — Perfect for REST, RPC, or event-driven micro-services with zero framework magic.

Frameworks like Laravel and Symfony excel at monolithic apps but introduce significant overhead:

- **Fast** — Faster than Flight, Laravel's router, Slim and Mezzio in every case measured, from 20 to 2,000 routes, in worker mode and under PHP-FPM, in-process and over HTTP. With `Router::loadRoutes()`, a PHP-FPM request costs under 5 µs of routing work at any size. Its URL matcher on its own is faster than Symfony's in every case measured, and faster than FastRoute's for routes with parameters and 404s from 200 routes up. In lean mode, `Router::match()` skips the request and response objects for the speed of a matcher-only router. See the [router comparison](benchmark/compare/README.md).
- **Lean Footprint** — No dependencies beyond the PSR interfaces. A request loads 25 small files, 3,153 lines, of which 1,128 are the PSR-7 interfaces, mostly docblocks (94 lines of code). In lean mode a request loads 5 files, 820 lines.
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

A placeholder matches one path segment (anything except `/`). If a placeholder name appears twice, both segments must hold the same value.

### Matching Order

Routes are matched in the order they are registered, and the first match wins. Register specific routes before general ones:

```php
Router::Get("/users/me", [UserController::class, 'me']);     // matches /users/me
Router::Get("/users/{id}", [UserController::class, 'show']); // matches everything else
```

If `/users/{id}` were registered first, it would also match `/users/me`.

Under the hood, the router builds a lookup table the first time it dispatches, and again whenever routes change. Exact paths are found with a single hash lookup. Parameterised routes are grouped by the static segments before their first placeholder (`/users` for `/users/{id}`), and each group is combined into one regex, so a request only tests the few routes that could match its path. In a long-running server such as OpenSwoole, the table is built once and reused for every request.

## Using Controllers

```php
Router::Get("/users",       [UserController::class, 'index']);
Router::Get("/users/{id}",  [UserController::class, 'show']);
Router::Post("/users",      [UserController::class, 'store']);
Router::Put("/users/{id}",  [UserController::class, 'update']);
Router::Patch("/users/{id}",[UserController::class, 'patch']);
Router::Delete("/users/{id}",[UserController::class, 'destroy']);
```

A controller is created the first time one of its routes is dispatched, then reused for every later request in the same process. There's one instance per class, shared by all of that class's routes. Under PHP-FPM that means at most one per request, and only for the controller that's actually used. Under OpenSwoole each worker keeps one instance per class, so don't keep per-request state in controller properties.

To build controllers some other way, such as from a dependency injection container, implement `ControllerResolverInterface` and register it:

```php
use Dromos\Routing\ControllerResolverInterface;

final class ContainerControllerResolver implements ControllerResolverInterface
{
    public function __construct(private ContainerInterface $container) {}

    public function resolve(string $class): object
    {
        return $this->container->get($class);
    }
}

Router::setControllerResolver(new ContainerControllerResolver($container));
```

A handler must return a `ResponseInterface`. Anything else becomes a 500 error.

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

## Lean mode

`Router::handle()` builds a request and a response, runs middleware and calls the handler for you. If you don't need those, `Router::match()` only finds the route, as a matcher-only router such as FastRoute does, and you call the handler yourself. In a worker it's three to five times faster per request; see the [router comparison](benchmark/compare/README.md).

```php
// public/index.php
Router::loadRoutes(__DIR__ . '/../routes.php', __DIR__ . '/../storage/cache/routes.php');

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = Router::match($_SERVER['REQUEST_METHOD'], $path);

switch ($route[0]) {
    case 200:
        [, [$class, $action], $params] = $route;
        header('Content-Type: application/json');
        echo json_encode((new $class())->{$action}(...$params));
        break;
    case 405:
        http_response_code(405);
        header('Allow: ' . implode(', ', $route[1]));
        break;
    default:
        http_response_code(404);
}
```

The result follows FastRoute's shape:

| Result | When |
|---|---|
| `[200, $handler, $params, $middleware]` | A route matches. `$handler` is the closure or `[Controller::class, 'method']` you registered, and `$params` holds the placeholder values by name. |
| `[404]` | No route matches the path. |
| `[405, $allowedMethods]` | Routes match the path, but only under other methods. |

- **It uses the same routes and caches** as `handle()`, including `loadRoutes()`, so you can switch between the two.
- **Middleware isn't run.** The route's own middleware is returned in `$middleware`, so check it if you rely on it, for example for authentication. Middleware added with `addMiddleware()` belongs to a `Router` instance and isn't seen by `match()`.
- **Pass the path only**, without the query string.

---

# Middleware

Dromos runs its own middleware and PSR-15 middleware. Either can be applied globally or per route or group.

## Global Middleware

```php
$router = new Router();
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new RateLimitMiddleware(100, 60));
```

## Per-Route / Per-Group Middleware

See [Route Groups](#route-groups) above for per-group middleware. Middleware can be an instance or a class name:

```php
$group->middleware(new AuthMiddleware($authenticator)); // an instance, built now
$group->middleware(RequireApiKey::class);               // a class name, built on first use
Router::setRouteMiddleware('GET', '/reports', [RequireApiKey::class]);
```

A class name is built by the controller resolver the first time a request reaches one of its routes, and reused after that, so a request never pays for middleware it doesn't use. Routes with class-name middleware can also be cached by `Router::loadRoutes()`. The default resolver calls the constructor with no arguments; for middleware that needs some, register a resolver backed by your container with `Router::setControllerResolver()`.

## PSR-15 Middleware

Middleware that implements `Psr\Http\Server\MiddlewareInterface` works anywhere Dromos middleware does: globally, per route or group, as an instance or a class name, and from the route cache.

```php
$router->addMiddleware(new SomePackage\Psr15Middleware());
$group->middleware(SomePackage\OtherPsr15Middleware::class);
```

- **Dromos middleware** implements `Dromos\Http\Middleware\MiddlewareInterface`, whose method is `handle()`. PSR-15's is `process()`. The two can be mixed in one chain.
- **Messages from other PSR-7 libraries are converted.** If a PSR-15 middleware passes on a request, or returns a response, built with another PSR-7 library (tested with Nyholm's), it's copied into a Dromos request or response before Dromos code sees it. Handlers can also return any PSR-7 response.
- **Handlers can ask for PSR-7 types:** a parameter typed `Psr\Http\Message\ServerRequestInterface` gets the request, and one typed `ResponseInterface` gets a new response, as the Dromos types do.

To use the router where a PSR-15 request handler is expected, for example by a PSR-15 server, wrap it. The wrapper accepts any PSR-7 server request:

```php
$handler = new Dromos\Http\PsrRequestHandler(new Router());
```

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

Counters are kept in a `RateLimitStore`. By default every `RateLimitMiddleware` shares one in-memory store, which only works in long-running processes (OpenSwoole, RoadRunner, ReactPHP). It tracks up to 10,000 client IPs and drops the oldest window beyond that. In tests, `RateLimitMiddleware::resetStore()` clears it.

**PHP-FPM:** each request has its own memory, so in-memory counters are never shared. Pass a store backed by shared storage instead. `RateLimitStore` has one method; this one uses APCu:

```php
use Dromos\Middleware\RateLimitEntry;
use Dromos\Middleware\RateLimitStore;

final class ApcuRateLimitStore implements RateLimitStore
{
    public function increment(string $key, int $windowSeconds): RateLimitEntry
    {
        $now = time();
        $window = intdiv($now, $windowSeconds) * $windowSeconds;
        $name = "ratelimit:$key:$window";

        // apcu_add only succeeds for the first request in a window; apcu_inc is atomic
        apcu_add($name, 0, $windowSeconds);
        return new RateLimitEntry((int) apcu_inc($name), $window);
    }
}

$router->addMiddleware(new RateLimitMiddleware(100, 60, new ApcuRateLimitStore()));
```

A Redis store works the same way with `INCR` and `EXPIRE`. APCu is shared between the workers of one PHP-FPM pool; use Redis to share limits between servers.

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

All response helpers return a new immutable Response instance, replacing any body and `Content-Type` it had:

```php
// JSON response (throws JsonException if the data can't be encoded)
$response->json(['key' => 'value'], 200);
$response->json(['url' => '/a/b'], 200, JSON_UNESCAPED_SLASHES); // optional json_encode() flags

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

A 405 also lists the allowed methods, under `details.available_methods` and in the `Allow` header. 404s and 405s are the client's errors, so they aren't written to the error log; 500s are, with the real exception message.

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

## Running under OpenSwoole

`SwooleEmitter` sends a response through OpenSwoole's HTTP server. It needs the `openswoole` extension (22 or later). Register your routes once, before the server starts, and every worker reuses the compiled route table:

```php
use Dromos\Http\Emitter\SwooleEmitter;
use Dromos\Http\Request;
use Dromos\Router;
use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use OpenSwoole\Http\Server;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/routes.php';

$router = new Router();
$server = new Server('0.0.0.0', 9501);

$server->on('request', function (SwooleRequest $in, SwooleResponse $out) use ($router) {
    $query = $in->server['query_string'] ?? '';
    $request = Request::create(
        method: $in->server['request_method'],
        uri: $in->server['request_uri'] . ($query === '' ? '' : '?' . $query),
        serverParams: array_change_key_case($in->server, CASE_UPPER),
        headers: $in->header ?? [],
        queryParams: $in->get ?? [],
        parsedBody: $in->post,
        body: (string) $in->rawContent(),
    );

    (new SwooleEmitter($out))->emit($router->handle($request));
});

$server->start();
```

- **JSON bodies:** OpenSwoole only parses form posts. Decode `rawContent()` into `parsedBody` yourself, as `benchmark/src/SwooleRequestFactory.php` does.
- **Shared controllers:** each worker keeps one controller instance per class for every request it handles, so don't keep per-request state in controller properties.
- **Headers:** every value of a header is sent, including several `Set-Cookie` headers.
- **Bodies:** a body that fits in 8 KB is sent in one piece with a `Content-Length`. A larger file or socket stream is sent in 8 KB chunks with chunked transfer encoding, so it never sits in memory whole.

### Other runtimes

Implement `EmitterInterface` to send responses anywhere else:

```php
use Dromos\Http\Emitter\EmitterInterface;
use Dromos\Http\Message\ResponseInterface;

class MyEmitter implements EmitterInterface
{
    public function emit(ResponseInterface $response): void
    {
        // Send the status, headers and body
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

Each file is loaded once per process: calling `load()` again with the same file does nothing, while a different file (say `.env.local`) still loads. Under OpenSwoole or RoadRunner, call it once at bootstrap, before the server starts. `$_ENV` and `putenv()` are shared by every request a worker handles, so read values into your configuration there rather than per request.

---

# Route Caching

Dromos has two route caches. Both store the compiled route table in a PHP file that opcache keeps in shared memory.

## Cached routes file (fastest)

Put your routes in their own file and load it with a cache file:

```php
// public/index.php
Router::loadRoutes(__DIR__ . '/../routes.php', __DIR__ . '/../storage/cache/routes.php');
(new Emitter())->emit((new Router())->handle(new Request()));
```

```php
// routes.php
use Dromos\Router;

Router::Get('/users/{id}', [UserController::class, 'show']);
Router::group('/admin', function ($group) {
    $group->middleware(RequireAdmin::class); // by class name, so it can be cached
    $group->get('/stats', [StatsController::class, 'index']);
});
```

The first request runs `routes.php` and stores the routes. Every later request dispatches straight from the cache: `routes.php` doesn't run, and nothing is registered or compiled. Under PHP-FPM, a request with 2,000 routes takes about the same time as one with 20.

- **It rebuilds itself when `routes.php` changes** (its size, modification time or inode). If `routes.php` includes other route files, delete the cache file when you deploy changes to them.
- **Only controller routes can be stored.** Closures and middleware *instances* can't be, so if `routes.php` defines any, it simply runs on every request. Give middleware by class name to keep them cacheable; they're built by the controller resolver when a request first reaches one of their routes.
- **Routes registered before `loadRoutes()`** mean the file runs on every request, so the order of all routes is kept. Routes added after it work as normal.
- Use a different file from `enableCache()` below.

## Compiled table cache

If your routes use closures, keep registering them on every request and let the cache skip compiling them:

```env
ROUTER_CACHE_FILE=/var/www/app/storage/cache/routes.php
```

```php
Router::initialize(); // Enables the cache if ROUTER_CACHE_FILE is set

// Register your routes as normal, on every request
Router::Get("/users/{id}", [UserController::class, 'show']);
```

Or manage it manually:

```php
Router::enableCache('/var/www/app/storage/cache/routes.php');
Router::clearCache();   // delete the file; it's rebuilt on the next request
Router::disableCache();
```

How it works:

- **It stays current on its own.** A signature of the registered routes is stored with the table. If a route is added, removed or changed, the file is rebuilt on the next request, so you don't need to clear it on deploy.
- **Every route type works.** Closures, controllers, groups and middleware all behave exactly as they do without the cache.
- **A broken cache never fails a request.** This is true of both caches: if the file can't be written, the error is logged and the routes are compiled as normal. A corrupt or outdated file is rebuilt.

## Preloading

Under PHP-FPM, add Dromos to opcache's preload so its classes are compiled once when PHP-FPM starts, instead of being loaded on each request:

```ini
opcache.preload=/var/www/app/vendor/pauldeano/dromos/preload.php
opcache.preload_user=www-data
```

Restart PHP-FPM after upgrading Dromos. If your app already has a preload script, `require` this one from it.

**Security:** cache files are run with `require`. Keep them in a directory only your application can write to, never a shared one such as `/tmp` on a multi-user server.

**Upgrading from 1.1:** the old JSON cache file is replaced automatically. In 1.1, routes in the cache file could be dispatched without being registered. That skipped their middleware and kept deleted routes alive. This still works in 1.2 but triggers an `E_USER_DEPRECATED` notice, and it stops working in 2.0. Register your routes on every request.

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

Run the test suite and static analysis with:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

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
