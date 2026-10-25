PHP 8.4.4, Darwin 25.2.0 arm64, Apple M3 Pro, 11 cores; JIT disable, Xdebug off; Dromos v1.4.0-1-gb9837c1

## In-process: µs per request, median of 5 runs, opcache on

| 20 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Worker: static, first | 1.62 ±4% | **0.28 ±5%** | 12.84 ±6% | 36.86 ±5% | 10.26 ±4% | 10.60 ±5% | 0.33 ±5% | 0.62 ±3% |
| Worker: static, last | 1.63 ±4% | **0.29 ±3%** | 23.96 ±4% | 37.31 ±3% | 10.24 ±3% | 10.88 ±3% | 0.33 ±7% | 0.62 ±3% |
| Worker: param, last | 2.40 ±2% | 0.81 ±4% | 25.80 ±5% | 39.92 ±3% | 11.05 ±5% | 11.52 ±2% | **0.61 ±7%** | 0.87 ±2% |
| Worker: 2 params, last | 2.77 ±4% | 0.98 ±3% | 26.96 ±3% | 41.01 ±3% | 11.34 ±4% | 11.92 ±3% | **0.69 ±3%** | 0.94 ±3% |
| Worker: POST, last | 1.70 ±4% | **0.29 ±3%** | 15.52 ±3% | 38.37 ±3% | 10.29 ±9% | 11.13 ±2% | 0.33 ±6% | 0.71 ±4% |
| Worker: 404 | 2.66 ±4% | 0.73 ±4% | 39.05 ±3% | 404.16 ±4% | 12.04 ±4% | 9.25 ±2% | **0.40 ±1%** | 0.78 ±2% |
| PHP-FPM: boot + param, last | 16.19 ±63% | **13.22 ±4%** | 74.30 ±6% | 162.67 ±3% | 64.06 ±3% | 111.47 ±3% | 25.73 ±12% | 54.35 ±3% |
| PHP-FPM cached: boot + param, last | 4.46 ±5% | 1.73 ±3% | 74.39 ±3% | 63.80 ±2% | 47.09 ±4% | 72.55 ±5% | 2.21 ±1% | **1.66 ±2%** |

| 200 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Worker: static, first | 1.63 ±1% | **0.28 ±4%** | 15.20 ±3% | 37.41 ±4% | 10.26 ±1% | 17.67 ±1% | 0.33 ±2% | 0.61 ±1% |
| Worker: static, last | 1.64 ±1% | **0.29 ±3%** | 145.53 ±1% | 37.25 ±4% | 10.26 ±1% | 22.35 ±5% | 0.34 ±3% | 0.62 ±1% |
| Worker: param, last | 2.40 ±5% | **0.82 ±4%** | 146.81 ±3% | 39.24 ±4% | 12.23 ±3% | 23.11 ±2% | 1.26 ±3% | 0.93 ±1% |
| Worker: 2 params, last | 2.82 ±6% | **0.98 ±1%** | 150.09 ±2% | 40.39 ±1% | 12.59 ±1% | 23.38 ±1% | 1.32 ±4% | 1.02 ±1% |
| Worker: POST, last | 1.73 ±7% | **0.29 ±3%** | 42.59 ±3% | 37.90 ±5% | 10.35 ±1% | 22.24 ±1% | 0.34 ±2% | 0.72 ±2% |
| Worker: 404 | 2.67 ±3% | **0.74 ±4%** | 301.35 ±2% | 3,425.31 ±4% | 13.02 ±2% | 10.32 ±2% | 1.03 ±4% | 0.84 ±2% |
| PHP-FPM: boot + param, last | 108.36 ±0% | **105.60 ±4%** | 516.22 ±2% | 1,124.01 ±4% | 641.90 ±3% | 810.58 ±2% | 459.82 ±2% | 550.86 ±2% |
| PHP-FPM cached: boot + param, last | 4.41 ±1% | 1.75 ±2% | 511.59 ±1% | 63.55 ±8% | 188.56 ±1% | 332.40 ±2% | 2.94 ±3% | **1.71 ±4%** |

| 2,000 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Worker: static, first | 1.64 ±1% | **0.28 ±1%** | 43.06 ±3% | 37.32 ±2% | 10.38 ±1% | 86.00 ±2% | 0.33 ±1% | 0.61 ±3% |
| Worker: static, last | 1.65 ±2% | **0.29 ±0%** | 1,386.21 ±1% | 37.24 ±3% | 10.41 ±2% | 127.78 ±3% | 0.34 ±1% | 0.62 ±1% |
| Worker: param, last | 2.43 ±3% | **0.83 ±2%** | 1,373.33 ±2% | 41.07 ±3% | 29.14 ±4% | 140.86 ±3% | 16.72 ±3% | 1.36 ±2% |
| Worker: 2 params, last | 2.85 ±2% | **1.00 ±1%** | 1,386.70 ±1% | 41.94 ±5% | 29.72 ±4% | 140.94 ±5% | 16.84 ±3% | 1.42 ±1% |
| Worker: POST, last | 1.71 ±1% | **0.29 ±2%** | 311.79 ±1% | 38.19 ±3% | 10.43 ±2% | 126.56 ±2% | 0.34 ±2% | 0.72 ±1% |
| Worker: 404 | 2.68 ±1% | **0.74 ±2%** | 2,987.88 ±2% | 33,561.07 ±1% | 31.30 ±3% | 28.03 ±4% | 15.35 ±3% | 1.26 ±1% |
| PHP-FPM: boot + param, last | 1,032.58 ±1% | **1,031.66 ±2%** | 4,930.39 ±0% | 11,070.23 ±3% | 33,395.50 ±1% | 33,893.97 ±2% | 31,877.00 ±3% | 5,591.69 ±2% |
| PHP-FPM cached: boot + param, last | 4.45 ±1% | **1.74 ±1%** | 4,889.26 ±3% | 66.29 ±1% | 1,653.33 ±3% | 2,924.60 ±3% | 20.47 ±3% | 2.16 ±1% |

## Matching only: µs per match, median of 5 runs, opcache on

Each router's URL matcher alone, read from its compiled cache: no request or response objects, middleware or handler call.

| 20 routes | dromos | fastroute | symfony |
|---|---|---|---|
| Worker: static, first | 0.18 ±1% | **0.18 ±0%** | 0.49 ±2% |
| Worker: static, last | 0.19 ±1% | **0.19 ±1%** | 0.49 ±2% |
| Worker: param, last | 0.51 ±1% | **0.42 ±1%** | 0.71 ±1% |
| Worker: 2 params, last | 0.65 ±1% | **0.46 ±1%** | 0.76 ±1% |
| Worker: POST, last | 0.19 ±2% | **0.19 ±1%** | 0.59 ±2% |
| Worker: 404 | 0.69 ±1% | **0.36 ±1%** | 0.78 ±1% |
| PHP-FPM cached: boot + param, last | **1.16 ±1%** | 1.96 ±1% | 1.52 ±1% |

| 200 routes | dromos | fastroute | symfony |
|---|---|---|---|
| Worker: static, first | 0.18 ±2% | **0.18 ±1%** | 0.49 ±1% |
| Worker: static, last | 0.20 ±1% | **0.19 ±1%** | 0.49 ±1% |
| Worker: param, last | **0.51 ±1%** | 0.98 ±1% | 0.78 ±1% |
| Worker: 2 params, last | **0.65 ±6%** | 1.03 ±1% | 0.82 ±2% |
| Worker: POST, last | 0.19 ±4% | **0.19 ±1%** | 0.59 ±2% |
| Worker: 404 | **0.69 ±2%** | 0.93 ±2% | 0.84 ±2% |
| PHP-FPM cached: boot + param, last | **1.15 ±1%** | 2.64 ±2% | 1.60 ±1% |

| 2,000 routes | dromos | fastroute | symfony |
|---|---|---|---|
| Worker: static, first | 0.19 ±2% | **0.18 ±1%** | 0.49 ±1% |
| Worker: static, last | 0.19 ±1% | **0.19 ±1%** | 0.49 ±2% |
| Worker: param, last | **0.51 ±3%** | 14.27 ±6% | 1.20 ±2% |
| Worker: 2 params, last | **0.66 ±1%** | 14.19 ±5% | 1.25 ±2% |
| Worker: POST, last | 0.19 ±1% | **0.19 ±1%** | 0.60 ±1% |
| Worker: 404 | **0.70 ±1%** | 13.90 ±6% | 1.25 ±2% |
| PHP-FPM cached: boot + param, last | **1.15 ±1%** | 16.96 ±4% | 2.11 ±1% |

## HTTP: µs per request, median of 3 runs, opcache on

PHP's built-in server and ab alone take 76 µs, and that is part of every number below.

| 20 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Static, last | 129 ±2% | **117 ±3%** | 188 ±3% | 403 ±5% | 233 ±7% | 378 ±5% | 137 ±2% | 171 ±2% |
| Param, last | 133 ±2% | **121 ±2%** | 187 ±2% | 431 ±2% | 215 ±1% | 376 ±2% | 145 ±3% | 169 ±4% |
| 404 | 125 ±4% | **116 ±2%** | 196 ±4% | 411 ±2% | 225 ±2% | 378 ±2% | 135 ±6% | 179 ±68% |
| Cached: static, last | 122 ±3% | **112 ±6%** | 194 ±3% | 230 ±3% | 204 ±2% | 268 ±3% | 120 ±3% | 129 ±8% |
| Cached: param, last | 126 ±7% | **110 ±1%** | 187 ±1% | 233 ±3% | 200 ±7% | 261 ±8% | **110 ±5%** | 112 ±1% |
| Cached: 404 | 123 ±3% | **107 ±8%** | 199 ±2% | 698 ±2% | 212 ±2% | 273 ±11% | 112 ±6% | 111 ±6% |

| 200 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Static, last | **226 ±26%** | 234 ±3% | 768 ±9% | 1,416 ±2% | 881 ±2% | 1,079 ±1% | 691 ±2% | 800 ±2% |
| Param, last | 239 ±3% | **219 ±4%** | 758 ±2% | 1,432 ±1% | 893 ±2% | 1,086 ±1% | 706 ±2% | 802 ±2% |
| 404 | 229 ±3% | **209 ±2%** | 923 ±2% | 1,552 ±1% | 912 ±8% | 1,074 ±1% | 704 ±1% | 805 ±2% |
| Cached: static, last | 122 ±4% | 122 ±9% | 733 ±1% | 223 ±0% | 416 ±1% | 592 ±2% | 108 ±2% | **107 ±2%** |
| Cached: param, last | 134 ±3% | 112 ±1% | 747 ±3% | 227 ±2% | 426 ±1% | 596 ±2% | 110 ±8% | **109 ±1%** |
| Cached: 404 | 122 ±2% | 110 ±0% | 920 ±4% | 3,905 ±1% | 434 ±2% | 581 ±2% | **105 ±1%** | 117 ±8% |

| 2,000 routes | dromos | dromos-lean | flight | laravel | slim | mezzio | fastroute | symfony |
|---|---|---|---|---|---|---|---|---|
| Static, last | 1,294 ±2% | **1,270 ±2%** | 5,087 ±2% | 10,623 ±1% | 31,799 ±0% | 33,197 ±0% | 31,269 ±1% | 5,936 ±1% |
| Param, last | 1,283 ±2% | **1,274 ±2%** | 5,077 ±3% | 10,753 ±1% | 31,771 ±1% | 33,405 ±0% | 31,313 ±1% | 5,919 ±0% |
| 404 | 1,294 ±2% | **1,242 ±7%** | 6,662 ±1% | 12,274 ±1% | 31,977 ±1% | 33,307 ±1% | 31,166 ±1% | 5,896 ±1% |
| Cached: static, last | 120 ±2% | 107 ±2% | 4,998 ±1% | 220 ±4% | 1,601 ±2% | 2,997 ±0% | **103 ±9%** | 105 ±1% |
| Cached: param, last | 125 ±62% | **107 ±0%** | 5,003 ±1% | 227 ±2% | 1,622 ±1% | 3,021 ±1% | 126 ±6% | 108 ±2% |
| Cached: 404 | 119 ±0% | 115 ±8% | 6,636 ±0% | 34,770 ±1% | 1,673 ±1% | 2,919 ±0% | 123 ±8% | **108 ±3%** |

## Footprint: one request with 200 routes, in a fresh process without opcache

| | Files | Lines of code | Peak memory |
|---|---|---|---|
| dromos | 25 | 3,424 | 678 KB |
| dromos-lean | 5 | 834 | 368 KB |
| flight | 10 | 4,332 | 906 KB |
| laravel | 75 | 26,248 | 4,125 KB |
| slim | 68 | 8,286 | 1,211 KB |
| mezzio | 103 | 19,198 | 2,899 KB |
| fastroute | 10 | 723 | 346 KB |
| symfony | 10 | 2,042 | 788 KB |

Package versions: flightphp/core v3.19.4, illuminate/routing v12.70.1, nikic/fast-route 1.3.1, symfony/routing v7.4.21, slim/slim 4.15.3, mezzio/mezzio 3.28.1, mezzio/mezzio-fastroute 3.14.0
Every sample: results/2026-10-25-darwin-arm64-php8.4.4/dataset.csv
