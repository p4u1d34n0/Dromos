# Router comparison

A head-to-head benchmark of Dromos against six other PHP routers, all running the same routes:

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

Each router is wired the way its documentation recommends, and uses its own route cache where it has one. Two of them do less work than the others:

- **FastRoute and Symfony Routing are matchers only.** They have no request object, response object, middleware or handler calling. Their adapters add the least glue a front controller needs: create the controller, pass the parameters, return the JSON. Treat their numbers as a lower bound rather than a like-for-like comparison.
- **Laravel here is `illuminate/routing` on its own**, without the HTTP kernel, service providers or exception handler that a Laravel app adds. These numbers flatter Laravel.
- **Slim and Mezzio register every route on each request, even with their caches.** Their caches hold FastRoute's dispatch data, not the routes, so registering them is still part of each PHP-FPM request. Both write their cache on the first request.

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
php run.php             # the full dataset: about 40 minutes; needs ab (ApacheBench)
php report.php results/<name>
```

`run.php` runs each script several times (`--runs=5`, `--http-runs=3`), each in a fresh process and one after another, and saves every run's raw JSON in `results/<date>-<os>-<arch>-php<version>/`. `report.php` turns that directory into Markdown tables and writes every sample to `dataset.csv`. Close other programs first: anything else using the CPU skews the numbers, and a wide run-to-run spread in the report is the sign.

The scripts also run on their own:

```bash
php -d opcache.enable_cli=1 -d opcache.file_update_protection=0 inprocess.php
php http.php
php footprint.php
```

They take `--routers=dromos,slim`, `--sizes=5,50,500`, `--budget=` or `--requests=`, and `--json=file`.

## Results

From [`results/2026-10-24-darwin-arm64-php8.4.4`](results/2026-10-24-darwin-arm64-php8.4.4/report.md): a MacBook Pro (Apple M3 Pro, 11 cores), PHP 8.4.4 with opcache and no JIT, 5 in-process runs and 3 HTTP runs. The numbers are µs per request, the median across runs; lower is better. The [full report](results/2026-10-24-darwin-arm64-php8.4.4/report.md) has every row at 20, 200 and 2,000 routes, with the run-to-run spread, and [`dataset.csv`](results/2026-10-24-darwin-arm64-php8.4.4/dataset.csv) has every sample. Run it on your own hardware before quoting it.

### In-process, 2,000 routes

| | Dromos | Flight | Laravel | Slim | Mezzio | FastRoute¹ | Symfony¹ |
|---|---|---|---|---|---|---|---|
| Worker: static route | 1.91 | 1,408 | 38.8 | 10.5 | 133 | 0.35 | 0.63 |
| Worker: route with a parameter | 2.63 | 1,406 | 42.1 | 31.0 | 145 | 18.1 | 1.37 |
| Worker: 404 | 2.91 | 3,027 | 34,235 | 31.8 | 28.5 | 16.9 | 1.26 |
| PHP-FPM: register, then one request | 1,058 | 5,061 | 11,634 | 33,873 | 35,322 | 32,529 | 5,704 |
| PHP-FPM with route cache | 5.38 | 5,085² | 68.5 | 1,697 | 3,016 | 21.6 | 2.22 |

¹ Matcher only, as described above. ² Flight has no route cache.

At 20 routes, the PHP-FPM rows are 16.4 and 5.39 µs for Dromos, against 65.2 and 48.2 µs for Slim and 113 and 74.0 µs for Mezzio.

### HTTP

PHP's built-in server and `ab` alone (a script that only echoes the JSON) take 78 µs, and that is part of every number below.

| Route with a parameter | Dromos | Flight | Laravel | Slim | Mezzio | FastRoute¹ | Symfony¹ |
|---|---|---|---|---|---|---|---|
| 20 routes | 131 | 184 | 452 | 219 | 386 | 138 | 180 |
| 20 routes, cached | 123 | 187² | 235 | 196 | 297 | 106 | 113 |
| 2,000 routes | 1,307 | 5,229 | 10,835 | 33,066 | 33,582 | 31,577 | 5,943 |
| 2,000 routes, cached | 123 | 5,133² | 228 | 1,642 | 3,055 | 125 | 112 |

### Footprint

One request with 200 routes, in a fresh process without opcache:

| | Files | Lines of code | Peak memory |
|---|---|---|---|
| Dromos | 20 | 1,909 | 550 KB |
| Flight | 10 | 4,332 | 904 KB |
| Laravel | 75 | 26,248 | 4,124 KB |
| Slim | 68 | 8,286 | 1,210 KB |
| Mezzio | 103 | 19,198 | 2,898 KB |
| FastRoute | 10 | 723 | 345 KB |
| Symfony | 10 | 2,042 | 786 KB |

### Reading the results

- **Of the full routers (Dromos, Flight, Laravel, Slim and Mezzio), Dromos is the fastest in every row** of the dataset: every route count, worker and PHP-FPM, in-process and over HTTP. It also loads the fewest lines of code and uses the least memory of the five.
- **Without a route cache, Dromos is the fastest of all seven** under PHP-FPM, at every size, because it compiles only the routes a request could match.
- **With caches, FastRoute and Symfony stay a few µs ahead per request.** They do less work: no request or response objects, no middleware. Over HTTP at 2,000 routes, Dromos (123 µs) sits between Symfony (112 µs) and FastRoute (125 µs).
- **Slim and Mezzio slow down as routes are added, even with their caches,** because they register every route on each request. Mezzio's worker numbers grow too: on every match, `mezzio-fastroute` searches the full route list for the matched route.
- **A few HTTP cells have a wide spread** (up to ±209% for Symfony at 200 routes, cached), where one of the three runs hit a slow patch. The median is robust to one bad run; the spread is shown so you can see where that happened.
