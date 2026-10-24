<?php

declare(strict_types=1);

namespace Dromos\Middleware;

/**
 * One key's request count in its current window.
 */
final readonly class RateLimitEntry
{
    /**
     * @param int $count       Requests counted in this window, including the current one
     * @param int $windowStart Unix time the window started
     */
    public function __construct(
        public int $count,
        public int $windowStart,
    ) {
    }
}
