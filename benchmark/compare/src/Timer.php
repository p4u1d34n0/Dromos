<?php

declare(strict_types=1);

namespace Bench;

final class Timer
{
    /**
     * µs per call in each of 5 rounds of $budget / 5 seconds. Each round
     * starts with one untimed call.
     *
     * @return list<float>
     */
    public static function rounds(callable $fn, float $budget): array
    {
        $rounds = [];
        for ($round = 0; $round < 5; $round++) {
            $fn();
            $n = 0;
            $start = hrtime(true);
            do {
                $fn();
                $n++;
            } while (hrtime(true) - $start < $budget / 5 * 1e9);
            $rounds[] = (hrtime(true) - $start) / $n / 1000;
        }
        return $rounds;
    }
}
