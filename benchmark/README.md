# Dromos + OpenSwoole Benchmark

A microservices benchmark demonstrating Dromos framework features running on
OpenSwoole's event-driven HTTP server. Three containerised services communicate
over an internal Docker network.

## Architecture

```
                         :9501 (exposed)
                    +------------------+
   Client  ------> |     Gateway      |
   (wrk)           |  CORS + RateLimit|
                    |  Auth + Proxy    |
                    +--------+---------+
                             |
                +------------+------------+
                |                         |
         :9502 (internal)          :9503 (internal)
    +----------------+        +------------------+
    |  User Service  |        | Product Service  |
    |  100 seed rows |        |  200 seed rows   |
    |  CRUD + Valid. |        |  CRUD + Valid.    |
    +----------------+        +------------------+
```

- **Gateway** (port 9501) -- public entry point. Applies CORS, rate limiting,
  and Bearer token authentication, then proxies requests to backend services.
- **User Service** (port 9502) -- in-memory user CRUD with pagination and
  input validation. Pre-seeded with 100 users.
- **Product Service** (port 9503) -- in-memory product CRUD with pagination and
  input validation. Pre-seeded with 200 products across 5 categories.

## Prerequisites

- Docker
- Docker Compose

## Quick Start

```bash
cd benchmark
docker compose build
docker compose up -d

# Run benchmarks:
docker compose --profile bench run --rm benchmark

# Cleanup:
docker compose down
```

## Dromos Features Demonstrated

| Feature                  | Where Used                                     |
|--------------------------|-------------------------------------------------|
| Static route registration| `users.php`, `products.php`                     |
| Route groups + prefix    | `gateway.php` -- `/api` group                   |
| Route parameters         | `GET /users/{id}`, `GET /products/{id}`          |
| PSR-15 middleware pipeline| Gateway global middleware stack                 |
| `AuthMiddleware`         | `/api` group -- Bearer token validation          |
| `CorsMiddleware`         | Gateway global -- cross-origin headers           |
| `RateLimitMiddleware`    | Gateway global -- 1,000,000 req/min per IP       |
| `Validator`              | `UserController::store/update`, `ProductController::store/update` |
| `Response::json()`       | All endpoints                                    |
| `Response::created()`    | `store` actions (201 Created)                    |
| `Response::noContent()`  | `destroy` actions (204 No Content)               |
| Controller-based routing | `[UserController::class, 'index']` syntax        |
| Closure-based routing    | Health checks, gateway proxy handlers            |
| Request attributes       | Route params via `$request->getAttribute('id')`  |
| Query parameter access   | Pagination via `$request->getQueryParams()`      |
| Body parsing             | JSON `$request->getParsedBody()` for create/update|

## Manual Testing

```bash
# Health check (no auth)
curl http://localhost:9501/health

# List users (requires auth)
curl -H "Authorization: Bearer benchmark-token" \
     http://localhost:9501/api/users

# List users with pagination
curl -H "Authorization: Bearer benchmark-token" \
     "http://localhost:9501/api/users?page=2&limit=10"

# Get a single user
curl -H "Authorization: Bearer benchmark-token" \
     http://localhost:9501/api/users/42

# Create a user
curl -X POST \
     -H "Authorization: Bearer benchmark-token" \
     -H "Content-Type: application/json" \
     -d '{"name":"Jane Doe","email":"jane@example.com"}' \
     http://localhost:9501/api/users

# Update a user
curl -X PUT \
     -H "Authorization: Bearer benchmark-token" \
     -H "Content-Type: application/json" \
     -d '{"name":"Jane Smith","email":"jane.smith@example.com"}' \
     http://localhost:9501/api/users/101

# Delete a user
curl -X DELETE \
     -H "Authorization: Bearer benchmark-token" \
     http://localhost:9501/api/users/101

# List products
curl -H "Authorization: Bearer benchmark-token" \
     http://localhost:9501/api/products

# Create a product
curl -X POST \
     -H "Authorization: Bearer benchmark-token" \
     -H "Content-Type: application/json" \
     -d '{"name":"Widget","price":29.99,"category":"electronics"}' \
     http://localhost:9501/api/products

# Test auth failure
curl http://localhost:9501/api/users
# Returns 401 Unauthorized

# Test validation failure
curl -X POST \
     -H "Authorization: Bearer benchmark-token" \
     -H "Content-Type: application/json" \
     -d '{"name":"A"}' \
     http://localhost:9501/api/users
# Returns 422 with validation errors
```

## Benchmark Results

Tested on Apple Silicon (Docker Desktop) with 4 OpenSwoole workers per service.
All numbers are requests/second. Latency values are p50 / p99.

### Requests per Second

| Endpoint | 1 conn | 10 conn | 50 conn | 100 conn | 200 conn |
|---|---:|---:|---:|---:|---:|
| GET /health (no middleware) | 12,000 | 65,953 | 196,948 | 249,464 | **288,169** |
| GET /api/users (full stack) | 3,198 | 11,718 | 7,924 | 4,725 | 3,233 |
| GET /api/users/50 (resource) | 3,240 | 12,661 | 12,736 | 12,688 | 12,589 |
| GET /api/products (2nd service) | 3,144 | 12,336 | 12,388 | 12,402 | 12,391 |
| POST /api/users (write+valid.) | 3,194 | 12,062 | 12,629 | 12,472 | 12,523 |

### p50 / p99 Latency

| Endpoint | 1 conn | 50 conn | 200 conn |
|---|---|---|---|
| GET /health | 82us / 101us | 211us / 610us | 601us / 1.60ms |
| GET /api/users | 306us / 454us | 5.99ms / 8.45ms | 60.85ms / 82.24ms |
| GET /api/users/50 | 304us / 393us | 3.69ms / 5.87ms | 15.43ms / 22.32ms |
| POST /api/users | 308us / 391us | 3.73ms / 5.70ms | 16.02ms / 20.50ms |

### Key Takeaways

- **288K req/s** on direct routes with sub-millisecond p99 latency
- **~12.5K req/s** sustained on proxied API routes through auth + CORS + rate limit + inter-service proxy
- p99 latency stays under 23ms at 200 concurrent connections for proxied single-resource routes
- Zero socket errors or non-2xx responses across all tests
- The `/api/users` list endpoint throughput drops at higher concurrency due to large paginated JSON payloads (20 users × full object per response)

## Adjusting Workers

Each service defaults to 4 OpenSwoole workers. Override via environment variable:

```bash
SWOOLE_WORKERS=8 docker compose up -d
```

Or set per-service in `docker-compose.yml`:

```yaml
environment:
  - SWOOLE_WORKERS=8
```

## HTTP Directory Case Note

Dromos uses `src/HTTP/` (uppercase) on disk but the PHP namespace is
`Dromos\Http` (mixed case). The `bootstrap.php` autoloader handles this
mismatch by falling back to an uppercase `HTTP/` directory lookup when the
initial case-sensitive path is not found. This ensures the benchmark runs
correctly on Linux's case-sensitive filesystem inside Docker.

## Router Dispatch Micro-benchmark

`dispatch.php` measures the router on its own, in-process, with no HTTP server:

```bash
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 benchmark/dispatch.php
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 benchmark/dispatch.php ../other/src  # compare another copy
```

- **worker** registers routes once, then dispatches repeatedly, as OpenSwoole and RoadRunner do.
- **fpm** registers every route and dispatches one request each time, as PHP-FPM does. It runs with and without the route cache.

`opcache.file_update_protection=0` lets opcache cache the route cache file the moment it's written. By default opcache ignores files modified in the last 2 seconds, which only matters in a benchmark that writes the file and then reads it straight away.

Each row creates a `Request`, dispatches it, and builds a JSON `Response`. Results are in µs per request (lower is better). These are the best of 3 runs on Apple Silicon with PHP 8.4 and opcache, comparing v1.1.0 with 1.2:

| Routes | Mode | Request | v1.1.0 | 1.2 | Speed-up |
|---|---|---|---:|---:|---:|
| 20 | worker | exact path, registered first | 2.4 | 2.3 | 1.0× |
| 20 | worker | exact path, registered last | 8.8 | 2.4 | 3.7× |
| 20 | worker | 1 parameter, registered last | 10.1 | 2.8 | 3.6× |
| 20 | worker | 2 parameters, registered last | 11.2 | 3.0 | 3.8× |
| 20 | worker | no match (404) | 21.4 | 11.1 | 1.9× |
| 20 | fpm | register all + 1 parameter | 15.7 | 15.7 | 1.0× |
| 20 | fpm | register all + 1 parameter, **route cache on** | 15.7 | 9.5 | 1.7× |
| 200 | worker | exact path, registered first | 2.4 | 2.4 | 1.0× |
| 200 | worker | exact path, registered last | 81.2 | 2.3 | 34.7× |
| 200 | worker | 1 parameter, registered last | 82.1 | 3.0 | 27.1× |
| 200 | worker | 2 parameters, registered last | 84.9 | 3.2 | 26.6× |
| 200 | worker | no match (404) | 110.9 | 11.5 | 9.6× |
| 200 | fpm | register all + 1 parameter | 133.1 | 116.5 | 1.1× |
| 200 | fpm | register all + 1 parameter, **route cache on** | 133.1 | 49.8 | 2.7× |
| 2,000 | worker | exact path, registered first | 2.4 | 2.4 | 1.0× |
| 2,000 | worker | exact path, registered last | 839.5 | 2.3 | 358.7× |
| 2,000 | worker | 1 parameter, registered last | 829.6 | 8.4 | 99.2× |
| 2,000 | worker | 2 parameters, registered last | 848.5 | 8.5 | 100.3× |
| 2,000 | worker | no match (404) | 1,029.7 | 17.6 | 58.4× |
| 2,000 | fpm | register all + 1 parameter | 1,322.1 | 1,110.3 | 1.2× |
| 2,000 | fpm | register all + 1 parameter, **route cache on** | 1,322.1 | 442.9 | 3.0× |

v1.1.0 tested each route in turn, so the cost grew with the size of the route table. 1.2 compiles the routes into a hash map and combined regexes, so matching cost stays nearly flat.

Under PHP-FPM, the route cache skips building the table. Most of the remaining time is registering the routes, which every request still does.

## Controller Construction Benchmark

`controllers.php` simulates a PHP-FPM request with controller routes. It registers 200 routes across 50 controller classes, then dispatches one request:

```bash
php -d opcache.enable_cli=1 benchmark/controllers.php empty   # empty constructors
php -d opcache.enable_cli=1 benchmark/controllers.php pdo     # constructors open a DB connection
```

Up to v1.1.0, each controller route created its own controller instance at registration: 200 instances per request in this benchmark, whichever route was hit. Controllers are now created on first dispatch, so this benchmark creates one.
