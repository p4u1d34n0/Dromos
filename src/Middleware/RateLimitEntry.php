<?php

declare(strict_types=1);

namespace Dromos\Middleware;

/**
 * Immutable value object representing a single rate-limit counter entry.
 */
final readonly class RateLimitEntry
{
    public function __construct(
        public int $count,
        public int $windowStart,
    ) {}
}
