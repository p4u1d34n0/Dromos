<?php

declare(strict_types=1);

namespace Dromos\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Composer's PSR-4 autoloader (and its optimised class map) only finds a
 * class when its file path matches the namespace exactly, including case.
 * A mismatch works on macOS but breaks on Linux and with `composer -o`.
 */
final class Psr4LayoutTest extends TestCase
{
    #[Test]
    public function test_every_class_lives_at_its_psr4_path(): void
    {
        $src = realpath(__DIR__ . '/../src');
        $checked = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            if (!preg_match('/^namespace\s+([\w\\\\]+);/m', $code, $namespace)
                || !preg_match('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $code, $class)
            ) {
                continue; // a view or script, not a class
            }

            $relative = substr($file->getPathname(), strlen($src) + 1);
            $expected = str_replace('\\', '/', substr($namespace[1] . '\\' . $class[1], strlen('Dromos\\'))) . '.php';
            $this->assertSame($expected, $relative, "{$namespace[1]}\\{$class[1]} is not at its PSR-4 path");
            $checked++;
        }
        $this->assertGreaterThan(30, $checked, 'expected to check every class in src/');
    }

    #[Test]
    public function test_source_files_import_classes_with_their_declared_case(): void
    {
        $src = realpath(__DIR__ . '/../src');
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all('/^use\s+(Dromos\\\\[\w\\\\]+)/m', (string) file_get_contents($file->getPathname()), $imports);
            foreach ($imports[1] as $import) {
                $path = $src . '/' . str_replace('\\', '/', substr($import, strlen('Dromos\\')));
                // A namespace import (e.g. use Dromos\Routing;) points at a directory
                $this->assertTrue(
                    $this->existsWithCase($path . '.php') || $this->existsWithCase($path),
                    "{$file->getFilename()} imports $import, which does not exist with that case"
                );
            }
        }
    }

    /** file_exists() ignores case on macOS, so compare each path segment exactly. */
    private function existsWithCase(string $path): bool
    {
        $dir = '/';
        foreach (array_filter(explode('/', $path), 'strlen') as $segment) {
            if (!is_dir($dir) || !in_array($segment, scandir($dir) ?: [], true)) {
                return false;
            }
            $dir = rtrim($dir, '/') . '/' . $segment;
        }
        return true;
    }
}
