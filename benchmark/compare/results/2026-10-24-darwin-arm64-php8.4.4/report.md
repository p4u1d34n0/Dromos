PHP 8.4.4, Darwin 25.2.0 arm64, Apple M3 Pro, 11 cores; JIT disable, Xdebug off; Dromos v1.3.0-2-g5034632

## In-process: µs per request, median of 5 runs, opcache on

| 20 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Worker: static, first | 1.53 ±7% | **0.28 ±3%** | 13.10 ±3% | 37.66 ±3% | 10.42 ±2% | 10.87 ±27% | 0.33 ±2% | 0.62 ±1% |
| Worker: static, last | 1.54 ±5% | **0.29 ±2%** | 24.02 ±2% | 37.84 ±4% | 10.31 ±4% | 11.27 ±3% | 0.34 ±3% | 0.62 ±3% |
| Worker: param, last | 2.43 ±3% | 0.82 ±3% | 25.79 ±2% | 40.52 ±2% | 11.21 ±4% | 11.74 ±1% | **0.62 ±4%** | 0.88 ±3% |
| Worker: 2 params, last | 2.86 ±9% | 1.01 ±2% | 28.91 ±6% | 41.81 ±2% | 11.73 ±3% | 11.99 ±2% | **0.70 ±1%** | 0.96 ±5% |
| Worker: POST, last | 1.54 ±107% | **0.29 ±4%** | 15.80 ±6% | 38.93 ±2% | 10.39 ±8% | 11.23 ±1% | 0.34 ±2% | 0.72 ±5% |
| Worker: 404 | 2.57 ±14% | 0.74 ±2% | 39.81 ±3% | 412.02 ±15% | 12.14 ±2% | 9.42 ±2% | **0.42 ±2%** | 0.79 ±2% |
| PHP-FPM: boot + param, last | 16.32 ±13% | **13.54 ±4%** | 76.05 ±3% | 165.27 ±9% | 65.16 ±5% | 113.31 ±4% | 25.57 ±3% | 54.21 ±1% |
| PHP-FPM cached: boot + param, last | 4.57 ±3% | 2.09 ±2% | 76.19 ±7% | 65.96 ±1% | 47.31 ±6% | 73.38 ±5% | 2.22 ±4% | **1.69 ±2%** |

| 200 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Worker: static, first | 1.54 ±5% | **0.29 ±3%** | 15.32 ±8% | 38.15 ±3% | 10.37 ±7% | 18.17 ±3% | 0.33 ±4% | 0.61 ±5% |
| Worker: static, last | 1.53 ±4% | **0.29 ±3%** | 152.74 ±10% | 38.90 ±8% | 10.47 ±17% | 22.97 ±13% | 0.34 ±3% | 0.63 ±2% |
| Worker: param, last | 2.37 ±6% | **0.83 ±3%** | 156.14 ±4% | 41.08 ±3% | 12.37 ±3% | 23.56 ±6% | 1.25 ±6% | 0.95 ±3% |
| Worker: 2 params, last | 2.77 ±7% | **1.02 ±4%** | 154.84 ±3% | 42.02 ±3% | 12.83 ±2% | 24.54 ±4% | 1.36 ±3% | 1.03 ±3% |
| Worker: POST, last | 1.55 ±7% | **0.29 ±3%** | 43.85 ±3% | 38.89 ±6% | 10.43 ±6% | 22.51 ±3% | 0.34 ±3% | 0.72 ±2% |
| Worker: 404 | 2.63 ±14% | **0.75 ±21%** | 310.88 ±5% | 3,476.54 ±3% | 13.18 ±3% | 10.64 ±5% | 1.04 ±8% | 0.86 ±3% |
| PHP-FPM: boot + param, last | 109.40 ±2% | **108.07 ±3%** | 535.59 ±12% | 1,158.23 ±2% | 642.27 ±6% | 830.77 ±4% | 472.58 ±5% | 555.56 ±1% |
| PHP-FPM cached: boot + param, last | 4.73 ±21% | 2.07 ±5% | 523.01 ±9% | 66.87 ±4% | 195.36 ±6% | 369.05 ±6% | 2.98 ±8% | **1.72 ±4%** |

| 2,000 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Worker: static, first | 1.53 ±2% | **0.28 ±3%** | 43.22 ±0% | 37.58 ±2% | 10.65 ±4% | 87.40 ±6% | 0.35 ±7% | 0.62 ±4% |
| Worker: static, last | 1.53 ±1% | **0.29 ±2%** | 1,403.64 ±2% | 38.15 ±3% | 10.63 ±5% | 133.57 ±22% | 0.35 ±4% | 0.63 ±6% |
| Worker: param, last | 2.33 ±2% | **0.82 ±2%** | 1,398.37 ±1% | 41.41 ±3% | 31.89 ±5% | 145.81 ±4% | 18.65 ±13% | 1.38 ±1% |
| Worker: 2 params, last | 2.71 ±2% | **0.99 ±2%** | 1,411.97 ±3% | 42.65 ±2% | 32.78 ±3% | 145.48 ±4% | 18.67 ±8% | 1.45 ±4% |
| Worker: POST, last | 1.52 ±1% | **0.29 ±6%** | 316.95 ±2% | 38.99 ±3% | 10.79 ±3% | 128.40 ±10% | 0.35 ±3% | 0.74 ±4% |
| Worker: 404 | 2.62 ±2% | **0.73 ±3%** | 3,014.97 ±1% | 35,541.54 ±8% | 32.44 ±2% | 30.12 ±6% | 16.99 ±74% | 1.29 ±3% |
| PHP-FPM: boot + param, last | 1,039.57 ±1% | **1,028.14 ±6%** | 4,976.61 ±2% | 11,382.87 ±3% | 34,715.56 ±8% | 35,866.53 ±7% | 34,431.94 ±7% | 6,335.66 ±27% |
| PHP-FPM cached: boot + param, last | 4.62 ±1% | **2.10 ±1%** | 5,001.00 ±2% | 71.22 ±17% | 1,716.73 ±10% | 3,395.93 ±15% | 22.34 ±4% | 2.19 ±5% |

## Matching only: µs per match, median of 5 runs, opcache on

Each router's URL matcher alone, read from its compiled cache: no request or response objects, middleware or handler call.

| 20 routes | dromos | fastroute | symfony |
|---|---|---|---|
| Worker: static, first | 0.18 ±0% | **0.18 ±1%** | 0.49 ±1% |
| Worker: static, last | 0.19 ±3% | **0.19 ±2%** | 0.49 ±1% |
| Worker: param, last | 0.53 ±3% | **0.43 ±2%** | 0.71 ±1% |
| Worker: 2 params, last | 0.65 ±3% | **0.47 ±3%** | 0.76 ±1% |
| Worker: POST, last | 0.19 ±1% | **0.19 ±2%** | 0.59 ±4% |
| Worker: 404 | 0.70 ±1% | **0.36 ±3%** | 0.78 ±2% |
| PHP-FPM cached: boot + param, last | **1.25 ±1%** | 1.98 ±3% | 1.50 ±1% |

| 200 routes | dromos | fastroute | symfony |
|---|---|---|---|
| Worker: static, first | 0.18 ±2% | **0.18 ±1%** | 0.49 ±1% |
| Worker: static, last | 0.20 ±1% | **0.19 ±0%** | 0.49 ±2% |
| Worker: param, last | **0.53 ±3%** | 0.98 ±1% | 0.78 ±1% |
| Worker: 2 params, last | **0.66 ±4%** | 1.03 ±1% | 0.83 ±2% |
| Worker: POST, last | 0.19 ±2% | **0.19 ±1%** | 0.60 ±2% |
| Worker: 404 | **0.70 ±2%** | 0.92 ±1% | 0.84 ±2% |
| PHP-FPM cached: boot + param, last | **1.25 ±2%** | 2.67 ±1% | 1.55 ±2% |

| 2,000 routes | dromos | fastroute | symfony |
|---|---|---|---|
| Worker: static, first | 0.18 ±1% | **0.18 ±1%** | 0.49 ±1% |
| Worker: static, last | 0.20 ±2% | **0.19 ±3%** | 0.49 ±1% |
| Worker: param, last | **0.54 ±5%** | 15.43 ±2% | 1.19 ±1% |
| Worker: 2 params, last | **0.68 ±10%** | 15.23 ±3% | 1.24 ±0% |
| Worker: POST, last | 0.20 ±6% | **0.19 ±2%** | 0.59 ±1% |
| Worker: 404 | **0.70 ±3%** | 14.76 ±2% | 1.25 ±0% |
| PHP-FPM cached: boot + param, last | **1.26 ±7%** | 18.19 ±1% | 2.00 ±1% |

## HTTP: µs per request, median of 3 runs, opcache on

PHP's built-in server and ab alone take 81 µs, and that is part of every number below.

| 20 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Static, last | 132 ±4% | **122 ±18%** | 199 ±5% | 395 ±4% | 223 ±4% | 370 ±6% | 145 ±4% | 173 ±4% |
| Param, last | 134 ±2% | **124 ±8%** | 188 ±17% | 424 ±3% | 223 ±3% | 374 ±5% | 156 ±10% | 180 ±6% |
| 404 | 131 ±5% | **128 ±6%** | 199 ±15% | 401 ±2% | 229 ±2% | 397 ±3% | 138 ±8% | 183 ±3% |
| Cached: static, last | 121 ±4% | **111 ±1%** | 188 ±3% | 229 ±3% | 207 ±3% | 267 ±11% | 112 ±8% | 112 ±3% |
| Cached: param, last | 120 ±22% | **113 ±4%** | 191 ±4% | 235 ±1% | 213 ±1% | 268 ±6% | 125 ±37% | 118 ±4% |
| Cached: 404 | 121 ±2% | **108 ±1%** | 201 ±0% | 704 ±2% | 211 ±2% | 271 ±4% | 121 ±34% | 127 ±8% |

| 200 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Static, last | 237 ±1% | **218 ±6%** | 760 ±0% | 1,427 ±2% | 909 ±2% | 1,105 ±1% | 695 ±2% | 817 ±2% |
| Param, last | 238 ±3% | **213 ±0%** | 766 ±6% | 1,423 ±2% | 903 ±2% | 1,096 ±1% | 695 ±2% | 816 ±1% |
| 404 | **225 ±2%** | 228 ±2% | 948 ±1% | 1,565 ±1% | 902 ±2% | 1,094 ±1% | 702 ±2% | 829 ±9% |
| Cached: static, last | 133 ±3% | 114 ±2% | 757 ±4% | 233 ±7% | 427 ±2% | 589 ±2% | **107 ±6%** | 110 ±1% |
| Cached: param, last | 124 ±3% | 124 ±2% | 763 ±2% | 234 ±3% | 431 ±1% | 594 ±2% | **108 ±2%** | 112 ±3% |
| Cached: 404 | 157 ±14% | 116 ±13% | 917 ±3% | 3,932 ±1% | 427 ±1% | 574 ±3% | **110 ±6%** | 114 ±6% |

| 2,000 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Static, last | 1,306 ±2% | **1,286 ±0%** | 5,139 ±8% | 10,881 ±5% | 31,992 ±3% | 34,312 ±0% | 31,023 ±1% | 5,979 ±1% |
| Param, last | **1,293 ±1%** | 1,298 ±1% | 5,074 ±2% | 10,929 ±3% | 32,220 ±2% | 33,893 ±1% | 31,490 ±1% | 6,025 ±2% |
| 404 | 1,286 ±1% | **1,275 ±2%** | 6,801 ±1% | 12,840 ±2% | 32,309 ±2% | 33,695 ±1% | 31,473 ±1% | 5,955 ±2% |
| Cached: static, last | 118 ±1% | 112 ±2% | 5,126 ±2% | 228 ±2% | 1,600 ±2% | 3,046 ±1% | 110 ±7% | **106 ±3%** |
| Cached: param, last | 126 ±2% | **109 ±0%** | 5,202 ±2% | 234 ±1% | 1,631 ±1% | 3,056 ±1% | 131 ±5% | 113 ±6% |
| Cached: 404 | 118 ±3% | 113 ±7% | 6,791 ±1% | 35,547 ±4% | 1,696 ±4% | 2,952 ±1% | 123 ±2% | **111 ±5%** |

## Footprint: one request with 200 routes, in a fresh process without opcache

| | Files | Lines of code | Peak memory |
|---|---|---|---|
| dromos | 19 | 1,970 | 564 KB |
| dromos-lean | 5 | 817 | 366 KB |
| flight | 10 | 4,332 | 906 KB |
| laravel | 75 | 26,248 | 4,126 KB |
| slim | 68 | 8,286 | 1,212 KB |
| mezzio | 103 | 19,198 | 2,900 KB |
| fastroute | 10 | 723 | 347 KB |
| symfony | 10 | 2,042 | 788 KB |

Package versions: flightphp/core v3.19.4, illuminate/routing v12.70.1, nikic/fast-route 1.3.1, symfony/routing v7.4.21, slim/slim 4.15.3, mezzio/mezzio 3.28.1, mezzio/mezzio-fastroute 3.14.0
Every sample: results/2026-10-24-darwin-arm64-php8.4.4/dataset.csv
