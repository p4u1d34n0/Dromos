PHP 8.4.4, Darwin 25.2.0 arm64, Apple M3 Pro, 11 cores; JIT disable, Xdebug off; Dromos 62c95e3

## In-process: µs per request, median of 5 runs, opcache on

| 20 routes | dromos | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|
| Worker: static, first | 1.64 ±1% | 13.07 ±2% | 37.65 ±0% | 10.50 ±3% | 10.82 ±4% | **0.34 ±2%** | 0.61 ±2% |
| Worker: static, last | 1.63 ±0% | 24.27 ±3% | 37.41 ±2% | 10.46 ±1% | 11.23 ±4% | **0.34 ±2%** | 0.61 ±1% |
| Worker: param, last | 2.31 ±1% | 25.74 ±1% | 40.07 ±1% | 11.18 ±2% | 11.87 ±14% | **0.62 ±3%** | 0.88 ±1% |
| Worker: 2 params, last | 2.73 ±1% | 27.33 ±2% | 41.09 ±1% | 11.65 ±6% | 11.94 ±2% | **0.70 ±1%** | 0.95 ±1% |
| Worker: POST, last | 1.67 ±2% | 15.63 ±1% | 38.68 ±0% | 10.47 ±3% | 11.23 ±2% | **0.35 ±8%** | 0.73 ±1% |
| Worker: 404 | 2.59 ±1% | 39.56 ±2% | 412.05 ±1% | 12.13 ±1% | 9.21 ±2% | **0.41 ±1%** | 0.80 ±2% |
| PHP-FPM: boot + param, last | **16.12 ±1%** | 75.54 ±3% | 166.60 ±1% | 64.53 ±5% | 112.92 ±1% | 25.56 ±0% | 54.59 ±2% |
| PHP-FPM cached: boot + param, last | 4.48 ±1% | 76.40 ±1% | 66.01 ±5% | 47.47 ±4% | 72.59 ±1% | 2.22 ±1% | **1.67 ±1%** |

| 200 routes | dromos | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|
| Worker: static, first | 1.64 ±1% | 15.39 ±2% | 37.79 ±1% | 10.37 ±1% | 17.79 ±1% | **0.34 ±1%** | 0.62 ±2% |
| Worker: static, last | 1.64 ±1% | 148.00 ±2% | 37.37 ±1% | 10.37 ±1% | 22.47 ±1% | **0.34 ±1%** | 0.63 ±2% |
| Worker: param, last | 2.38 ±2% | 148.95 ±3% | 40.80 ±3% | 12.41 ±1% | 23.19 ±2% | 1.26 ±1% | **0.95 ±2%** |
| Worker: 2 params, last | 2.78 ±3% | 151.55 ±2% | 41.89 ±1% | 12.83 ±1% | 23.74 ±2% | 1.34 ±1% | **1.03 ±2%** |
| Worker: POST, last | 1.65 ±3% | 43.02 ±2% | 38.78 ±1% | 10.47 ±1% | 22.24 ±1% | **0.34 ±1%** | 0.72 ±1% |
| Worker: 404 | 2.61 ±2% | 303.92 ±1% | 3,425.75 ±2% | 13.20 ±1% | 10.41 ±2% | 1.03 ±1% | **0.85 ±1%** |
| PHP-FPM: boot + param, last | **109.23 ±2%** | 520.77 ±2% | 1,149.70 ±1% | 640.60 ±3% | 809.71 ±1% | 467.01 ±2% | 545.93 ±1% |
| PHP-FPM cached: boot + param, last | 4.62 ±2% | 517.70 ±1% | 66.60 ±1% | 189.23 ±1% | 333.20 ±2% | 2.98 ±1% | **1.74 ±1%** |

| 2,000 routes | dromos | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|
| Worker: static, first | 1.66 ±1% | 43.20 ±0% | 37.31 ±0% | 10.45 ±2% | 85.80 ±2% | **0.35 ±2%** | 0.61 ±2% |
| Worker: static, last | 1.65 ±4% | 1,384.92 ±2% | 37.41 ±1% | 10.44 ±1% | 132.24 ±6% | **0.35 ±1%** | 0.62 ±1% |
| Worker: param, last | 2.38 ±5% | 1,385.24 ±2% | 41.11 ±1% | 30.90 ±3% | 145.96 ±4% | 17.57 ±5% | **1.38 ±2%** |
| Worker: 2 params, last | 2.89 ±4% | 1,393.80 ±1% | 42.01 ±0% | 30.67 ±2% | 143.02 ±3% | 18.07 ±5% | **1.46 ±1%** |
| Worker: POST, last | 1.66 ±3% | 314.27 ±1% | 38.55 ±1% | 10.50 ±2% | 128.20 ±5% | **0.35 ±2%** | 0.73 ±1% |
| Worker: 404 | 2.62 ±3% | 3,060.01 ±2% | 34,263.07 ±2% | 31.41 ±2% | 28.18 ±3% | 15.30 ±5% | **1.26 ±1%** |
| PHP-FPM: boot + param, last | **1,019.06 ±2%** | 4,987.14 ±2% | 11,214.81 ±2% | 33,699.65 ±3% | 35,297.47 ±2% | 31,912.97 ±1% | 5,640.59 ±1% |
| PHP-FPM cached: boot + param, last | 4.65 ±2% | 4,946.72 ±1% | 68.26 ±2% | 1,676.19 ±1% | 2,972.71 ±1% | 21.52 ±4% | **2.17 ±2%** |

## Matching only: µs per match, median of 5 runs, opcache on

Each router's URL matcher alone, read from its compiled cache: no request or response objects, middleware or handler call.

| 20 routes | dromos | fastroute | symfony |
|---|---|---|---|
| Worker: static, first | 0.18 ±1% | **0.18 ±1%** | 0.50 ±71% |
| Worker: static, last | 0.19 ±1% | **0.19 ±1%** | 0.50 ±43% |
| Worker: param, last | 0.60 ±4% | **0.43 ±1%** | 0.72 ±2% |
| Worker: 2 params, last | 0.74 ±1% | **0.47 ±1%** | 0.77 ±3% |
| Worker: POST, last | 0.19 ±1% | **0.19 ±1%** | 0.59 ±3% |
| Worker: 404 | 0.74 ±1% | **0.36 ±1%** | 0.79 ±4% |
| PHP-FPM cached: boot + param, last | **1.36 ±2%** | 1.99 ±1% | 1.52 ±4% |

| 200 routes | dromos | fastroute | symfony |
|---|---|---|---|
| Worker: static, first | 0.19 ±2% | **0.18 ±1%** | 0.49 ±3% |
| Worker: static, last | 0.20 ±1% | **0.19 ±1%** | 0.49 ±3% |
| Worker: param, last | **0.60 ±1%** | 0.98 ±0% | 0.78 ±3% |
| Worker: 2 params, last | **0.74 ±3%** | 1.02 ±1% | 0.83 ±2% |
| Worker: POST, last | 0.20 ±1% | **0.19 ±1%** | 0.59 ±2% |
| Worker: 404 | **0.74 ±2%** | 0.93 ±2% | 0.84 ±1% |
| PHP-FPM cached: boot + param, last | **1.37 ±2%** | 2.65 ±3% | 1.57 ±5% |

| 2,000 routes | dromos | fastroute | symfony |
|---|---|---|---|
| Worker: static, first | 0.18 ±3% | **0.18 ±2%** | 0.49 ±2% |
| Worker: static, last | 0.20 ±1% | **0.19 ±1%** | 0.49 ±2% |
| Worker: param, last | **0.60 ±1%** | 14.38 ±4% | 1.21 ±1% |
| Worker: 2 params, last | **0.75 ±2%** | 14.27 ±5% | 1.24 ±2% |
| Worker: POST, last | 0.20 ±1% | **0.19 ±2%** | 0.60 ±2% |
| Worker: 404 | **0.74 ±1%** | 13.79 ±2% | 1.25 ±1% |
| PHP-FPM cached: boot + param, last | **1.41 ±1%** | 17.16 ±3% | 2.08 ±2% |

## HTTP: µs per request, median of 3 runs, opcache on

PHP's built-in server and ab alone take 77 µs, and that is part of every number below.

| 20 routes | dromos | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|
| Static, last | **131 ±0%** | 194 ±5% | 404 ±4% | 220 ±3% | 371 ±10% | 138 ±4% | 176 ±2% |
| Param, last | **131 ±2%** | 190 ±2% | 432 ±2% | 226 ±8% | 389 ±1% | 143 ±10% | 168 ±5% |
| 404 | 143 ±21% | 199 ±2% | 419 ±3% | 222 ±1% | 386 ±4% | **141 ±5%** | 183 ±1% |
| Cached: static, last | 123 ±4% | 186 ±4% | 248 ±8% | 200 ±2% | 301 ±7% | **110 ±8%** | 117 ±4% |
| Cached: param, last | 125 ±3% | 192 ±2% | 235 ±9% | 207 ±3% | 281 ±6% | **114 ±19%** | 129 ±2% |
| Cached: 404 | 128 ±5% | 202 ±0% | 710 ±1% | 224 ±4% | 259 ±8% | 117 ±8% | **116 ±5%** |

| 200 routes | dromos | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|
| Static, last | **237 ±3%** | 765 ±5% | 1,413 ±2% | 895 ±5% | 1,116 ±4% | 715 ±2% | 854 ±4% |
| Param, last | **233 ±2%** | 759 ±1% | 1,439 ±1% | 904 ±7% | 1,101 ±6% | 725 ±2% | 848 ±5% |
| 404 | **219 ±3%** | 932 ±1% | 1,697 ±34% | 910 ±9% | 1,106 ±5% | 728 ±5% | 817 ±5% |
| Cached: static, last | 128 ±4% | 756 ±1% | 253 ±278% | 423 ±6% | 598 ±4% | **108 ±9%** | 116 ±6% |
| Cached: param, last | 124 ±1% | 756 ±1% | 237 ±375% | 431 ±4% | 610 ±2% | **111 ±38%** | 114 ±8% |
| Cached: 404 | 131 ±6% | 933 ±3% | 3,941 ±3% | 436 ±1% | 609 ±4% | 111 ±6% | **110 ±7%** |

| 2,000 routes | dromos | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|
| Static, last | **1,265 ±2%** | 5,263 ±2% | 10,821 ±6% | 31,988 ±0% | 33,452 ±1% | 31,822 ±3% | 5,926 ±1% |
| Param, last | **1,275 ±1%** | 5,232 ±5% | 10,760 ±3% | 31,789 ±1% | 33,236 ±1% | 31,180 ±3% | 5,935 ±1% |
| 404 | **1,262 ±0%** | 6,905 ±2% | 12,336 ±3% | 32,114 ±1% | 33,299 ±2% | 30,834 ±3% | 5,945 ±1% |
| Cached: static, last | 122 ±5% | 5,150 ±4% | 227 ±4% | 1,603 ±1% | 3,054 ±2% | **107 ±9%** | 111 ±3% |
| Cached: param, last | 123 ±4% | 5,116 ±4% | 230 ±12% | 1,651 ±1% | 3,058 ±2% | 126 ±8% | **111 ±6%** |
| Cached: 404 | 123 ±2% | 6,854 ±3% | 34,977 ±1% | 1,665 ±2% | 2,999 ±2% | 126 ±2% | **112 ±2%** |

## Footprint: one request with 200 routes, in a fresh process without opcache

| | Files | Lines of code | Peak memory |
|---|---|---|---|
| dromos | 20 | 1,931 | 555 KB |
| flight | 10 | 4,332 | 906 KB |
| laravel | 75 | 26,248 | 4,126 KB |
| slim | 68 | 8,286 | 1,212 KB |
| mezzio | 103 | 19,198 | 2,900 KB |
| fastroute | 10 | 723 | 346 KB |
| symfony | 10 | 2,042 | 788 KB |

Package versions: flightphp/core v3.19.4, illuminate/routing v12.70.1, nikic/fast-route 1.3.1, symfony/routing v7.4.21, slim/slim 4.15.3, mezzio/mezzio 3.28.1, mezzio/mezzio-fastroute 3.14.0
Every sample: results/2026-10-24-darwin-arm64-php8.4.4/dataset.csv
