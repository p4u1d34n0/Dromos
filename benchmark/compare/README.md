# Router comparison

A head-to-head benchmark of Dromos, in full and in lean mode, against six other PHP routers, all running the same routes:

- Dromos, with request and response objects, middleware and handler calling
- Dromos in lean mode: `Router::match()` and the handler call, with no request or response objects or middleware
- [Flight](https://docs.flightphp.com/) 3
- Laravel's router (`illuminate/routing` 12)
- [Slim](https://www.slimframework.com/) 4, with `slim/psr7`
- [Mezzio](https://docs.mezzio.dev/) 3, with FastRoute, laminas-diactoros and laminas-servicemanager, wired as its skeleton app is
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

Each router is wired the way its documentation recommends, and uses its own route cache where it has one. Three of them do less work than the others:

- **Dromos in lean mode does the same work as the FastRoute and Symfony adapters below:** it matches, then calls the controller with the parameters. It is what an API that needs no middleware would run.
- **FastRoute and Symfony Routing are matchers only.** They have no request object, response object, middleware or handler calling. Their adapters add the least glue a front controller needs: create the controller, pass the parameters, return the JSON. Treat their numbers as a lower bound rather than a like-for-like comparison.
- **Laravel here is `illuminate/routing` on its own**, without the HTTP kernel, service providers or exception handler that a Laravel app adds. These numbers flatter Laravel.
- **Slim and Mezzio register every route on each request, even with their caches.** Their caches hold FastRoute's dispatch data, not the routes, so registering them is still part of each PHP-FPM request. Both write their cache on the first request.

| Script | What it runs |
|---|---|
| `inprocess.php` | The routing work per request, in-process. **worker**: routes registered once, then one request after another (OpenSwoole, RoadRunner, FrankenPHP). **fpm**: register the routes and handle one request, every time. **fpm cached**: the same, using each router's cache. |
| `matchers.php` | Matching only: Dromos's, FastRoute's and Symfony's URL matchers read from their compiled caches, with no request, response, middleware or handler call. This is where the three compare like for like. **worker**: one path after another. **fpm cached**: load the compiled routes and match one path, every time. Each matcher must return the expected status, action and parameters before it is timed (see `Matchers::verify()`). |
| `http.php` | Real HTTP requests to PHP's built-in server with opcache, one PHP request per HTTP request, as under PHP-FPM. This also counts loading each router's files and sending its response. |
| `footprint.php` | Files, lines of code and memory that one request needs, in a fresh process. |

Dromos's cached mode uses `Router::loadRoutes()` with a routes file.

## Running it

```bash
cd benchmark/compare
composer install
php run.php             # the full dataset: about 90 minutes; needs ab (ApacheBench)
php report.php results/<name>
```

`run.php` runs each script several times (`--runs=5`, `--http-runs=3`), each in a fresh process and one after another, and saves every run's raw JSON in `results/<date>-<os>-<arch>-php<version>/`. `report.php` turns that directory into Markdown tables and writes every sample to `dataset.csv`. Close other programs first: anything else using the CPU skews the numbers, and a wide run-to-run spread in the report is the sign.

The scripts also run on their own:

```bash
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 inprocess.php
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 matchers.php
php http.php
php footprint.php
```

They take `--routers=dromos,slim`, `--sizes=5,50,500`, `--budget=` or `--requests=`, and `--json=file`.

## Results

From [`results/2026-10-25-darwin-arm64-php8.4.4`](results/2026-10-25-darwin-arm64-php8.4.4/report.md): Dromos 1.4.0 with the faster cache loading from #42, on a MacBook Pro (Apple M3 Pro, 11 cores), PHP 8.4.4 with opcache and no JIT. It has 5 in-process and matcher runs, and 3 HTTP runs. The numbers are µs per request, the median across runs; lower is better. The [full report](results/2026-10-25-darwin-arm64-php8.4.4/report.md) has every row at 20, 200 and 2,000 routes, with the run-to-run spread, and [`dataset.csv`](results/2026-10-25-darwin-arm64-php8.4.4/dataset.csv) has every sample. Run it on your own hardware before quoting it. The [earlier dataset](results/2026-10-24-darwin-arm64-php8.4.4/report.md), from before the PSR-7 work and #42, is kept for comparison.

### In-process, 2,000 routes

| | Dromos | Dromos lean | Flight | Laravel | Slim | Mezzio | FastRoute¹ | Symfony¹ |
|---|---|---|---|---|---|---|---|---|
| Worker: static route | 1.65 | 0.29 | 1,386 | 37.2 | 10.4 | 128 | 0.34 | 0.62 |
| Worker: route with a parameter | 2.43 | 0.83 | 1,373 | 41.1 | 29.1 | 141 | 16.7 | 1.36 |
| Worker: 404 | 2.68 | 0.74 | 2,988 | 33,561 | 31.3 | 28.0 | 15.4 | 1.26 |
| PHP-FPM: register, then one request | 1,033 | 1,032 | 4,930 | 11,070 | 33,396 | 33,894 | 31,877 | 5,592 |
| PHP-FPM with route cache | 4.45 | 1.74 | 4,889² | 66.3 | 1,653 | 2,925 | 20.5 | 2.16 |

### In-process, 20 routes

| | Dromos | Dromos lean | Flight | Laravel | Slim | Mezzio | FastRoute¹ | Symfony¹ |
|---|---|---|---|---|---|---|---|---|
| Worker: static route | 1.63 | 0.29 | 24.0 | 37.3 | 10.2 | 10.9 | 0.33 | 0.62 |
| Worker: route with a parameter | 2.40 | 0.81 | 25.8 | 39.9 | 11.1 | 11.5 | 0.61 | 0.87 |
| Worker: 404 | 2.66 | 0.73 | 39.1 | 404 | 12.0 | 9.25 | 0.40 | 0.78 |
| PHP-FPM: register, then one request | 16.2 | 13.2 | 74.3 | 163 | 64.1 | 111 | 25.7 | 54.4 |
| PHP-FPM with route cache | 4.46 | 1.73 | 74.4² | 63.8 | 47.1 | 72.6 | 2.21 | 1.66 |

¹ Matcher only, as described above. ² Flight has no route cache.

### Matching only

The URL matchers alone, read from their compiled caches, in µs per match:

| | Routes | Dromos | FastRoute | Symfony |
|---|---|---|---|---|
| Worker: static route | 20 | 0.19 | 0.19 | 0.49 |
| Worker: route with a parameter | 20 | 0.51 | 0.42 | 0.71 |
| Worker: 404 | 20 | 0.69 | 0.36 | 0.78 |
| PHP-FPM with route cache | 20 | 1.16 | 1.96 | 1.52 |
| Worker: static route | 2,000 | 0.19 | 0.19 | 0.49 |
| Worker: route with a parameter | 2,000 | 0.51 | 14.3 | 1.20 |
| Worker: 404 | 2,000 | 0.70 | 13.9 | 1.25 |
| PHP-FPM with route cache | 2,000 | 1.15 | 17.0 | 2.11 |

### HTTP

PHP's built-in server and `ab` alone (a script that only echoes the JSON) take 76 µs, and that is part of every number below.

| Route with a parameter | Dromos | Dromos lean | Flight | Laravel | Slim | Mezzio | FastRoute¹ | Symfony¹ |
|---|---|---|---|---|---|---|---|---|
| 20 routes | 133 | 121 | 187 | 431 | 215 | 376 | 145 | 169 |
| 20 routes, cached | 126 | 110 | 187² | 233 | 200 | 261 | 110 | 112 |
| 2,000 routes | 1,283 | 1,274 | 5,077 | 10,753 | 31,771 | 33,405 | 31,313 | 5,919 |
| 2,000 routes, cached | 125 | 107 | 5,003² | 227 | 1,622 | 3,021 | 126 | 108 |

### Footprint

One request with 200 routes, in a fresh process without opcache:

| | Files | Lines of code | Peak memory |
|---|---|---|---|
| Dromos | 25 | 3,424 | 678 KB |
| Dromos lean | 5 | 834 | 368 KB |
| Flight | 10 | 4,332 | 906 KB |
| Laravel | 75 | 26,248 | 4,125 KB |
| Slim | 68 | 8,286 | 1,211 KB |
| Mezzio | 103 | 19,198 | 2,899 KB |
| FastRoute | 10 | 723 | 346 KB |
| Symfony | 10 | 2,042 | 788 KB |

Of full Dromos's 3,424 lines, 1,128 are the PSR-7 interfaces, mostly docblocks.

### Reading the results

- **Of the full routers (Dromos, Flight, Laravel, Slim and Mezzio), Dromos is the fastest in every row** of the dataset: every route count, worker and PHP-FPM, in-process and over HTTP. It also loads the fewest lines of code and uses the least memory of the five.
- **Dromos in lean mode is the fastest of all eight in every in-process row at 2,000 routes, and in every row at 200 routes bar one:** PHP-FPM with a cache, where Symfony takes 1.71 µs and lean mode 1.75 µs, inside the run-to-run spread. At 20 routes it is the fastest for static routes, POSTs and uncached PHP-FPM, and behind in four rows: FastRoute for a route with a parameter (0.61 against 0.81 µs), with two parameters (0.69 against 0.98 µs) and a 404 (0.40 against 0.73 µs), and Symfony for PHP-FPM with a cache (1.66 against 1.73 µs).
- **Those gaps have two causes.** In the 20-route worker rows it is the matcher: FastRoute's is faster at that size, as the matching-only rows show. Under PHP-FPM with a cache, Dromos's matcher is the fastest of the three (1.16 µs against Symfony's 1.52 µs), so the gap is the fixed cost of loading the router around it.
- **Lean mode's cached PHP-FPM request is about 0.35 µs faster than in the earlier dataset** (1.73 against 2.09 µs at 20 routes, 1.74 against 2.10 µs at 2,000), because #42 loads the cache with fewer checks. Full Dromos's worker requests are within 0.12 µs of the earlier dataset, the cost of PSR-7 compliance.
- **Lean mode saves 1.3 to 1.9 µs a request in a worker, and about 2.7 µs under PHP-FPM with a cache,** against full Dromos, and loads 5 files instead of 25. It is for APIs that need no middleware; full Dromos is still faster than every other full router.
- **Over HTTP, lean mode is the fastest in every 20-route row,** level with FastRoute for a cached route with a parameter. With caches at 200 and 2,000 routes, it is the fastest or within 15 µs of the fastest, behind FastRoute and Symfony. Each of these requests includes 76 µs of server and `ab` time, so the gaps between the three are small against the total.
- **Slim and Mezzio slow down as routes are added, even with their caches,** because they register every route on each request. Mezzio's worker numbers grow too: on every match, `mezzio-fastroute` searches the full route list for the matched route.
- **A few cells have a wide spread,** up to ±68% (Symfony, 404 over HTTP, 20 routes), where one run hit a slow patch. The median is robust to one bad run; the spread is shown so you can see where that happened.
