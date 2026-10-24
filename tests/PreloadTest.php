<?php

declare(strict_types=1);

namespace Dromos\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PreloadTest extends TestCase
{
    private const ROOT = __DIR__ . '/..';

    /**
     * Run PHP with preload.php as the opcache preload script and return what
     * the code printed.
     */
    private function runPreloaded(string $code): string
    {
        if (!function_exists('opcache_get_status') && !extension_loaded('Zend OPcache')) {
            $this->markTestSkipped('opcache is not available');
        }
        $command = [
            PHP_BINARY, '-d', 'opcache.enable=1', '-d', 'opcache.enable_cli=1',
            '-d', 'opcache.preload=' . realpath(self::ROOT . '/preload.php'),
            '-r', $code,
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors);
        $this->assertSame('', $errors);
        return $output;
    }

    #[Test]
    public function test_every_dromos_class_is_preloaded_without_an_autoloader(): void
    {
        $expected = [];
        $src = realpath(self::ROOT . '/src') . '/';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $expected[] = 'Dromos\\' . str_replace('/', '\\', substr($file->getPathname(), strlen($src), -4));
            }
        }

        // No autoloader is registered in the child process, so only preloaded classes exist
        $output = $this->runPreloaded(sprintf(<<<'PHP'
            $missing = array_filter(%s, fn($c) => !class_exists($c, false) && !interface_exists($c, false) && !trait_exists($c, false));
            echo json_encode(array_values($missing));
            PHP, var_export($expected, true)));

        $this->assertGreaterThan(25, count($expected));
        $this->assertSame([], json_decode($output, true, 512, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function test_a_preloaded_router_dispatches_a_request(): void
    {
        $output = $this->runPreloaded(<<<'PHP'
            $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/7'];
            Dromos\Router::Get('/users/{id}', fn(Dromos\Http\Response $r, string $id) => $r->json(['id' => $id]));
            echo (new Dromos\Router())->handle(new Dromos\Http\Request())->getBody();
            PHP);

        $this->assertSame('{"id":"7"}', $output);
    }
}
