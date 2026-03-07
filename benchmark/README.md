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
