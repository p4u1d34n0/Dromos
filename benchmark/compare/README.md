# Router comparison

A head-to-head benchmark of Dromos against four other PHP routers, all running the same routes:

- [Flight](https://docs.flightphp.com/) 3
- Laravel's router (`illuminate/routing` 12)
- [FastRoute](https://github.com/nikic/FastRoute) 1.3
- Symfony Routing 7

The competitors are installed from this directory's own `composer.json`, so Dromos itself gains no dependencies.

## What it measures

Each route set has 5, 50 or 500 "resources". Each resource has four controller routes, giving 20, 200 or 2,000 routes in total:

```
GET  /res{n}
GET  /res{n}/{id}
GET  /res{n}/{id}/items/{item}
POST /res{n}
```

Every handler returns a small JSON body. Before anything is timed, each router must return the expected status and body for every request; otherwise the run fails (see `Contenders::verify()`).

Each router is wired the way its documentation recommends, and uses its own route cache where it has one. Two of them do less work than the others:

- **FastRoute and Symfony Routing are matchers only.** They have no request object, response object, middleware or handler calling. Their adapters add the least glue a front controller needs: create the controller, pass the parameters, return the JSON. Treat their numbers as a lower bound rather than a like-for-like comparison.
- **Laravel here is `illuminate/routing` on its own**, without the HTTP kernel, service providers or exception handler that a Laravel app adds. These numbers flatter Laravel.

| Script | What it runs |
|---|---|
| `inprocess.php` | The routing work per request, in-process. **worker**: routes registered once, then one request after another (OpenSwoole, RoadRunner, FrankenPHP). **fpm**: register the routes and handle one request, every time. **fpm cached**: the same, using each router's cache. |
| `http.php` | Real HTTP requests to PHP's built-in server with opcache, one PHP request per HTTP request, as under PHP-FPM. This also counts loading each router's files and sending its response. |
| `footprint.php` | Files, lines of code and memory that one request needs, in a fresh process. |

Dromos's cached mode uses `Router::loadRoutes()` with a routes file.

## Running it

```bash
cd benchmark/compare
composer install
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 inprocess.php
php http.php            # needs ab (ApacheBench)
php footprint.php
```

`inprocess.php` and `http.php` take `--routers=dromos,flight`, `--sizes=5,50,500`, and `--budget=` or `--requests=`.

## Results

Run on 24 Oct 2026, on a MacBook (Apple silicon, 11 cores), PHP 8.4.4 with opcache and no JIT. The numbers are µs per request; lower is better. Run it on your own hardware before quoting it.

### In-process

| 2,000 routes | Dromos | Flight | Laravel | FastRoute¹ | Symfony¹ |
|---|---|---|---|---|---|
| Worker: static route | 1.9 | 1,462 | 38.6 | 0.38 | 0.64 |
| Worker: route with a parameter | 2.5 | 1,517 | 42.2 | 17.6 | 1.38 |
| Worker: 404 | 2.8 | 3,065 | 35,961 | 15.7 | 1.26 |
| PHP-FPM: register, then one request | 1,019 | 5,031 | 11,620 | 32,591 | 5,955 |
| PHP-FPM with route cache | 5.4 | 5,107² | 72.7 | 20.4 | 2.25 |

| 20 routes | Dromos | Flight | Laravel | FastRoute¹ | Symfony¹ |
|---|---|---|---|---|---|
| Worker: route with a parameter | 2.5 | 26.3 | 40.6 | 0.62 | 0.90 |
| Worker: 404 | 2.9 | 40.0 | 422 | 0.41 | 0.78 |
| PHP-FPM: register, then one request | 17.3 | 77.1 | 168 | 26.1 | 55.1 |
| PHP-FPM with route cache | 5.3 | 76.1² | 67.1 | 2.20 | 1.68 |

¹ Matcher only, as described above. ² Flight has no route cache.

Matching alone, without any request or response objects, for a route with a parameter: Dromos takes 0.55 µs at every size. FastRoute takes 0.37 µs at 20 routes and 17.4 µs at 2,000. Symfony takes 0.59 µs at 20 routes and 1.06 µs at 2,000.

### HTTP

PHP's built-in server and `ab` alone (a script that only echoes the JSON) take 79 µs, and that is part of every number below.

| | Dromos | Flight | Laravel | FastRoute¹ | Symfony¹ |
|---|---|---|---|---|---|
| 20 routes, param route | 125 | 182 | 418 | 139 | 177 |
| 20 routes, cached | 118 | 188² | 270 | 107 | 108 |
| 2,000 routes, param route | 1,284 | 5,147 | 11,045 | 31,227 | 5,996 |
| 2,000 routes, cached | 115 | 5,177² | 227 | 120 | 107 |
| 2,000 routes, cached, 404 | 123 | 7,002² | 35,435 | 118 | 106 |

### Footprint

One request with 200 routes, in a fresh process without opcache:

| | Files | Lines of code | Peak memory |
|---|---|---|---|
| Dromos | 20 | 1,936 | 561 KB |
| Flight | 10 | 4,332 | 904 KB |
| Laravel | 75 | 26,248 | 4,119 KB |
| FastRoute | 10 | 723 | 387 KB |
| Symfony | 10 | 2,042 | 786 KB |

### Reading the results

- **Dromos is faster than Flight and Laravel in every row**, at every size, in both worker and PHP-FPM mode.
- **Without a route cache, Dromos is the fastest of all five** under PHP-FPM, because it compiles only the routes a request could match.
- **With caches, FastRoute and Symfony stay a few µs ahead per request.** They do less work: no request or response objects, no middleware. Over HTTP, the difference is within about 10 µs of a 79 µs floor.
- **Dromos's matcher is the fastest of the three from 200 routes up.** At 20 routes FastRoute's is faster by about 0.2 µs.
