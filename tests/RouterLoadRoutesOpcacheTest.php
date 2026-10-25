<?php

declare(strict_types=1);

namespace Dromos\Tests;

use Dromos\Router;
use Dromos\Routing\RouteCacheFile;
use Dromos\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Router::loadRoutes() under opcache, which the test process doesn't run:
 * each test runs its requests in a child PHP process with opcache on (see
 * Support/opcache-requests.php).
 *
 * While opcache holds both the routes file and the cache as current, PHP
 * would run the routes file as it was, so the router doesn't check it: a
 * change counts when PHP would notice changed code.
 */
#[CoversClass(Router::class)]
#[CoversClass(RouteCacheFile::class)]
final class RouterLoadRoutesOpcacheTest extends TestCase
{
    use TemporaryDirectory;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    #[Test]
    public function test_a_cache_pinned_by_opcache_is_used_without_checking_the_routes_file(): void
    {
        $requests = $this->requests(['v1', 'request', 'request', 'request', 'v2', 'request']);

        $this->assertSame(1, $requests[0]['ran'], 'the first request builds the cache');
        $this->assertSame([0, 0], [$requests[1]['ran'], $requests[2]['ran']], 'later requests use it');
        // Checking the routes file would find it changed and run it again
        // (from opcache, unchanged); pinned, it isn't checked or run
        $this->assertSame(['a' => 200, 'b' => 404, 'ran' => 0], array_intersect_key($requests[3], ['a' => 1, 'b' => 1, 'ran' => 1]));
    }

    #[Test]
    public function test_invalidating_the_routes_file_in_opcache_makes_a_change_count(): void
    {
        $requests = $this->requests(['v1', 'request', 'request', 'v2', 'invalidate-routes', 'request', 'request']);

        $this->assertSame(['a' => 200, 'b' => 200, 'ran' => 1], array_intersect_key($requests[2], ['a' => 1, 'b' => 1, 'ran' => 1]));
        $this->assertSame(['a' => 200, 'b' => 200, 'ran' => 0], array_intersect_key($requests[3], ['a' => 1, 'b' => 1, 'ran' => 1]), 'and the rebuilt cache is used');
    }

    #[Test]
    public function test_invalidating_the_cache_file_in_opcache_makes_a_change_count(): void
    {
        $requests = $this->requests(['v1', 'request', 'request', 'v2', 'invalidate-cache', 'invalidate-routes', 'request']);

        $this->assertSame(['a' => 200, 'b' => 200, 'ran' => 1], array_intersect_key($requests[2], ['a' => 1, 'b' => 1, 'ran' => 1]));
    }

    #[Test]
    public function test_a_cache_file_dropped_from_opcache_makes_the_routes_file_checked(): void
    {
        // Another worker rebuilding the cache invalidates it in opcache, as here
        $requests = $this->requests(['v1', 'request', 'request', 'v2', 'invalidate-cache', 'request']);

        $this->assertSame(1, $requests[2]['ran'], 'the changed routes file is noticed and run');
    }

    #[Test]
    public function test_a_hit_after_checking_compiles_the_routes_file_so_later_requests_are_pinned(): void
    {
        // As after an opcache reset: neither file is in opcache, and the routes file is unchanged
        $requests = $this->requests(['v1', 'request', 'invalidate-cache', 'invalidate-routes', 'request', 'v2', 'request']);

        $this->assertSame(0, $requests[1]['ran'], 'the unchanged routes file is checked, not run');
        $this->assertTrue($requests[1]['routesInOpcache'], 'but compiled into opcache');
        $this->assertSame(['b' => 404, 'ran' => 0], array_intersect_key($requests[2], ['b' => 1, 'ran' => 1]), 'so the next request is pinned');
    }

    #[Test]
    public function test_with_timestamps_validated_a_change_counts_once_opcache_revalidates(): void
    {
        $requests = $this->requests(['v1', 'request', 'request', 'v2', 'request', 'request'], revalidateEvery: 0);

        $this->assertSame(0, $requests[1]['ran']);
        $this->assertSame(['a' => 200, 'b' => 200, 'ran' => 1], array_intersect_key($requests[2], ['a' => 1, 'b' => 1, 'ran' => 1]));
        $this->assertSame(['b' => 200, 'ran' => 0], array_intersect_key($requests[3], ['b' => 1, 'ran' => 1]), 'and the rebuilt cache is pinned');
    }

    #[Test]
    public function test_with_timestamps_validated_a_change_waits_for_opcache_to_revalidate(): void
    {
        // As PHP keeps running changed code until opcache revalidates it
        $requests = $this->requests(['v1', 'request', 'request', 'v2', 'request'], revalidateEvery: 60);

        $this->assertSame(['b' => 404, 'ran' => 0], array_intersect_key($requests[2], ['b' => 1, 'ran' => 1]));
    }

    /**
     * @param list<string> $steps
     * @param int|null     $revalidateEvery opcache.revalidate_freq, or null for opcache.validate_timestamps off
     * @return list<array{a: int, b: int, ran: int, routesInOpcache: bool}>
     */
    private function requests(array $steps, ?int $revalidateEvery = null): array
    {
        $php = escapeshellarg(PHP_BINARY);
        $options = '-d opcache.enable_cli=1 -d opcache.file_update_protection=0 '
            . ($revalidateEvery === null ? '-d opcache.validate_timestamps=0' : "-d opcache.validate_timestamps=1 -d opcache.revalidate_freq=$revalidateEvery");
        if (trim((string) shell_exec("$php $options -r 'echo (int) (function_exists(\"opcache_get_status\") && opcache_get_status(false));' 2>/dev/null")) !== '1') {
            $this->markTestSkipped('opcache is not available to the PHP CLI.');
        }

        $dir = $this->temporaryDirectory();
        $arguments = array_map('escapeshellarg', [
            __DIR__ . '/Support/opcache-requests.php',
            dirname(__DIR__) . '/vendor/autoload.php',
            "$dir/routes.php",
            "$dir/routes.cache.php",
            ...$steps,
        ]);
        $output = (string) shell_exec("$php $options " . implode(' ', $arguments) . ' 2>&1');

        $requests = json_decode($output, true);
        $this->assertIsArray($requests, "The child process failed: $output");
        /** @var list<array{a: int, b: int, ran: int, routesInOpcache: bool}> $requests */
        return $requests;
    }
}
