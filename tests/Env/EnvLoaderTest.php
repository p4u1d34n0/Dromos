<?php

declare(strict_types=1);

namespace Dromos\Tests\Env;

use Dromos\Env\EnvLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvLoader::class)]
final class EnvLoaderTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = sys_get_temp_dir() . '/dromos_envloader_test_' . uniqid();
        mkdir($this->fixtureDir, 0755, true);
    }

    protected function tearDown(): void
    {
        EnvLoader::reset();

        foreach (glob($this->fixtureDir . '/{,.}[!.,!..]*', GLOB_BRACE) as $file) {
            unlink($file);
        }
        rmdir($this->fixtureDir);

        unset($_ENV['DROMOS_TEST_KEY'], $_ENV['DB_HOST'], $_ENV['DB_PORT'], $_ENV['APP_DEBUG']);
        putenv('DROMOS_TEST_KEY');
        putenv('DB_HOST');
        putenv('DB_PORT');
        putenv('APP_DEBUG');
    }

    public function test_it_loads_env_file_into_superglobal(): void
    {
        $envFile = $this->createEnvFile("DROMOS_TEST_KEY=hello_world\n");

        EnvLoader::load($envFile);

        $this->assertSame('hello_world', $_ENV['DROMOS_TEST_KEY']);
        $this->assertSame('hello_world', getenv('DROMOS_TEST_KEY'));
    }

    public function test_it_ignores_second_load_call(): void
    {
        $envFile = $this->createEnvFile("DROMOS_TEST_KEY=first\n");

        EnvLoader::load($envFile);
        $this->assertSame('first', $_ENV['DROMOS_TEST_KEY']);

        file_put_contents($envFile, "DROMOS_TEST_KEY=second\n");

        EnvLoader::load($envFile);
        $this->assertSame('first', $_ENV['DROMOS_TEST_KEY']);
    }

    public function test_reset_allows_reload(): void
    {
        $envFile = $this->createEnvFile("DROMOS_TEST_KEY=original\n");

        EnvLoader::load($envFile);
        $this->assertSame('original', $_ENV['DROMOS_TEST_KEY']);

        unset($_ENV['DROMOS_TEST_KEY']);
        putenv('DROMOS_TEST_KEY');
        EnvLoader::reset();

        file_put_contents($envFile, "DROMOS_TEST_KEY=reloaded\n");

        EnvLoader::load($envFile);
        $this->assertSame('reloaded', $_ENV['DROMOS_TEST_KEY']);
    }

    public function test_it_skips_missing_file(): void
    {
        $missingPath = $this->fixtureDir . '/nonexistent.env';

        EnvLoader::load($missingPath);

        $this->assertArrayNotHasKey('DROMOS_TEST_KEY', $_ENV);
    }

    public function test_it_skips_comments_and_malformed_lines(): void
    {
        $content = "# This is a comment\nDB_HOST=localhost\nMALFORMED_LINE_NO_EQUALS\n  # Indented comment\nDB_PORT=3306\n";
        $envFile = $this->createEnvFile($content);

        EnvLoader::load($envFile);

        $this->assertSame('localhost', $_ENV['DB_HOST']);
        $this->assertSame('3306', $_ENV['DB_PORT']);
        $this->assertArrayNotHasKey('MALFORMED_LINE_NO_EQUALS', $_ENV);
    }

    public function test_get_returns_default_when_key_missing(): void
    {
        $this->assertSame('fallback', EnvLoader::get('DROMOS_NONEXISTENT_KEY', 'fallback'));
    }

    public function test_get_returns_null_when_key_missing_and_no_default(): void
    {
        $this->assertNull(EnvLoader::get('DROMOS_NONEXISTENT_KEY'));
    }

    public function test_get_retrieves_loaded_value(): void
    {
        $envFile = $this->createEnvFile("APP_DEBUG=0\n");

        EnvLoader::load($envFile);

        $this->assertSame('0', EnvLoader::get('APP_DEBUG', 'fallback'));
    }

    public function test_it_skips_lines_with_empty_key(): void
    {
        $content = "=some_value\nDB_HOST=localhost\n";
        $envFile = $this->createEnvFile($content);

        EnvLoader::load($envFile);

        $this->assertArrayNotHasKey('', $_ENV);
        $this->assertSame('localhost', $_ENV['DB_HOST']);
    }

    private function createEnvFile(string $content): string
    {
        $path = $this->fixtureDir . '/.env';
        file_put_contents($path, $content);

        return $path;
    }
}
