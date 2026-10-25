<?php

declare(strict_types=1);

namespace Dromos\Tests\Routing;

use Dromos\Routing\CachedRoutes;
use Dromos\Routing\CompiledRoutes;
use Dromos\Routing\RouteCacheFile;
use Dromos\Routing\RouteCompiler;
use Dromos\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RouteCacheFile::class)]
#[CoversClass(CachedRoutes::class)]
final class RouteCacheFileTest extends TestCase
{
    use TemporaryDirectory;

    private string $path;
    private int $compiles = 0;
    private string $errorLog;

    protected function setUp(): void
    {
        $this->path = $this->temporaryDirectory() . '/routes.php';
        $this->errorLog = (string) ini_get('error_log');
        ini_set('error_log', '/dev/null');
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->errorLog);
        $this->removeTemporaryDirectory();
    }

    #[Test]
    public function test_it_compiles_once_then_reads_the_cache_file(): void
    {
        $routes = $this->routes(['/users', '/users/{id}']);

        $first = (new RouteCacheFile($this->path))->remember($routes, $this->compiler());
        $second = (new RouteCacheFile($this->path))->remember($routes, $this->compiler());

        $this->assertSame(1, $this->compiles);
        $this->assertSame($first->toArray(), $second->toArray());
        $this->assertSame('/users/{id}', $second->match('GET', '/users/3')?->url);
    }

    #[Test]
    public function test_changed_routes_are_recompiled_and_saved(): void
    {
        $cache = new RouteCacheFile($this->path);
        $cache->remember($this->routes(['/a']), $this->compiler());

        $table = $cache->remember($this->routes(['/a', '/b']), $this->compiler());
        $cache->remember($this->routes(['/a', '/b']), $this->compiler());

        $this->assertSame(2, $this->compiles);
        $this->assertNotNull($table->match('GET', '/b'));
    }

    #[Test]
    public function test_a_different_controller_method_changes_the_signature(): void
    {
        $a = ['GET' => ['/x' => [self::class, 'one']]];
        $b = ['GET' => ['/x' => [self::class, 'two']]];

        $this->assertNotSame(CachedRoutes::signatureOf($a), CachedRoutes::signatureOf($b));
        $this->assertSame(CachedRoutes::signatureOf($a), CachedRoutes::signatureOf($a));
    }

    #[Test]
    public function test_the_file_is_php_that_returns_the_entry(): void
    {
        $routes = ['GET' => ['/x' => [self::class, 'show'], '/y' => static fn() => null]];
        (new RouteCacheFile($this->path))->remember($routes, $this->compiler());

        $this->assertStringStartsWith('<?php', (string) file_get_contents($this->path));
        $this->assertSame('0644', substr(sprintf('%o', fileperms($this->path)), -4));
        $this->assertSame(['GET' => ['/x' => [self::class, 'show']]], (new RouteCacheFile($this->path))->load()?->handlers);
        $this->assertSame([], glob($this->temporaryDirectory() . '/*.tmp'));
    }

    #[Test]
    public function test_a_corrupt_file_is_rebuilt(): void
    {
        file_put_contents($this->path, '<?php return [');

        $table = (new RouteCacheFile($this->path))->remember($this->routes(['/a']), $this->compiler());

        $this->assertNotNull($table->match('GET', '/a'));
        $this->assertNotNull((new RouteCacheFile($this->path))->load());
    }

    #[Test]
    public function test_a_file_in_an_unexpected_format_is_rebuilt(): void
    {
        file_put_contents($this->path, '<?php return ["version" => 0];');

        (new RouteCacheFile($this->path))->remember($this->routes(['/a']), $this->compiler());

        $this->assertSame(1, $this->compiles);
        $this->assertNotNull((new RouteCacheFile($this->path))->load());
    }

    #[Test]
    public function test_a_legacy_json_cache_file_is_replaced_without_being_printed(): void
    {
        file_put_contents($this->path, '{"GET":{"/a":["App\\\\Controller","show"]}}');

        $this->expectOutputString('');
        $this->assertNull((new RouteCacheFile($this->path))->load());

        (new RouteCacheFile($this->path))->remember($this->routes(['/a']), $this->compiler());
        $this->assertStringStartsWith('<?php', (string) file_get_contents($this->path));
    }

    #[Test]
    public function test_an_unwritable_directory_does_not_fail_the_request(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can write to read-only directories');
        }
        chmod($this->temporaryDirectory(), 0500);

        $cache = new RouteCacheFile($this->path);
        $table = $cache->remember($this->routes(['/a']), $this->compiler());

        $this->assertNotNull($table->match('GET', '/a'));
        $this->assertFileDoesNotExist($this->path);
        $this->assertFalse($cache->save(new CachedRoutes('x', $table, [])));
    }

    #[Test]
    public function test_clear_removes_the_file(): void
    {
        $cache = new RouteCacheFile($this->path);
        $cache->remember($this->routes(['/a']), $this->compiler());

        $cache->clear();
        $cache->clear();

        $this->assertFileDoesNotExist($this->path);
        $this->assertNull($cache->load());
    }

    /**
     * @param string[] $urls GET routes, in order
     * @return array<string, array<string, \Closure>>
     */
    private function routes(array $urls): array
    {
        return ['GET' => array_fill_keys($urls, static fn() => null)];
    }

    /** @return \Closure(array<string, array<string, mixed>>): CompiledRoutes */
    private function compiler(): \Closure
    {
        return function (array $routes): CompiledRoutes {
            $this->compiles++;
            return (new RouteCompiler())->compile($routes);
        };
    }
}
