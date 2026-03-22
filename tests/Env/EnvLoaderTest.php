<?php

declare(strict_types=1);

namespace Dromos\Tests\Env;

use Dromos\Env\EnvLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvLoader::class)]
final class EnvLoaderTest extends TestCase
{
    private string $tempDir;

    /** @var array<string, string> */
    private array $originalEnv;

    protected function setUp(): void
    {
        $this->originalEnv = $_ENV;
        $this->tempDir = sys_get_temp_dir() . '/dromos_env_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $addedKeys = array_diff_key($_ENV, $this->originalEnv);
        foreach ($addedKeys as $key => $value) {
            putenv($key);
        }

        $_ENV = $this->originalEnv;

        $files = glob($this->tempDir . '/{,.}*', GLOB_BRACE);
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->tempDir);
    }

    public function test_it_loads_valid_key_value_pairs(): void
    {
        $path = $this->createEnvFile("APP_NAME=Dromos\nAPP_ENV=testing");

        EnvLoader::load($path);

        $this->assertSame('Dromos', $_ENV['APP_NAME']);
        $this->assertSame('testing', $_ENV['APP_ENV']);
    }

    public function test_it_skips_comment_lines(): void
    {
        $path = $this->createEnvFile("# This is a comment\nAPP_NAME=Dromos\n  # Indented comment");

        EnvLoader::load($path);

        $this->assertSame('Dromos', $_ENV['APP_NAME']);
        $this->assertArrayNotHasKey('# This is a comment', $_ENV);
    }

    #[DataProvider('blankLineProvider')]
    public function test_it_skips_blank_and_whitespace_only_lines(string $content): void
    {
        $path = $this->createEnvFile($content);

        EnvLoader::load($path);

        $this->assertSame('value', $_ENV['VALID_KEY']);
    }

    public static function blankLineProvider(): iterable
    {
        return [
            'blank line between entries' => ["VALID_KEY=value\n\nOTHER=test"],
            'whitespace-only line' => ["VALID_KEY=value\n   \nOTHER=test"],
            'tab-only line' => ["VALID_KEY=value\n\t\t\nOTHER=test"],
        ];
    }

    public function test_it_skips_lines_without_equals_separator(): void
    {
        $path = $this->createEnvFile("MALFORMED_LINE\nAPP_NAME=Dromos");

        EnvLoader::load($path);

        $this->assertSame('Dromos', $_ENV['APP_NAME']);
        $this->assertArrayNotHasKey('MALFORMED_LINE', $_ENV);
    }

    public function test_it_skips_lines_with_empty_key_name(): void
    {
        $path = $this->createEnvFile("=empty_key_value\nAPP_NAME=Dromos");

        EnvLoader::load($path);

        $this->assertSame('Dromos', $_ENV['APP_NAME']);
        $this->assertArrayNotHasKey('', $_ENV);
    }

    public function test_it_does_not_overwrite_existing_env_variables(): void
    {
        $_ENV['EXISTING_VAR'] = 'original';
        $path = $this->createEnvFile('EXISTING_VAR=overwritten');

        EnvLoader::load($path);

        $this->assertSame('original', $_ENV['EXISTING_VAR']);
    }

    public function test_it_handles_values_containing_equals_signs(): void
    {
        $path = $this->createEnvFile('DATABASE_URL=mysql://user:pass@host/db?option=value');

        EnvLoader::load($path);

        $this->assertSame('mysql://user:pass@host/db?option=value', $_ENV['DATABASE_URL']);
    }

    public function test_it_silently_returns_when_file_does_not_exist(): void
    {
        $envBefore = $_ENV;

        EnvLoader::load('/nonexistent/path/.env');

        $this->assertSame($envBefore, $_ENV);
    }

    public function test_get_returns_env_value(): void
    {
        $_ENV['TEST_KEY'] = 'test_value';

        $result = EnvLoader::get('TEST_KEY');

        $this->assertSame('test_value', $result);
    }

    public function test_get_returns_default_when_key_is_missing(): void
    {
        unset($_ENV['MISSING_KEY']);

        $result = EnvLoader::get('MISSING_KEY', 'fallback');

        $this->assertSame('fallback', $result);
    }

    public function test_get_returns_null_when_key_is_missing_and_no_default(): void
    {
        unset($_ENV['MISSING_KEY']);

        $result = EnvLoader::get('MISSING_KEY');

        $this->assertNull($result);
    }

    public function test_get_falls_back_to_getenv_when_not_in_env_superglobal(): void
    {
        unset($_ENV['GETENV_ONLY']);
        putenv('GETENV_ONLY=from_process');

        $result = EnvLoader::get('GETENV_ONLY');

        $this->assertSame('from_process', $result);

        putenv('GETENV_ONLY');
    }

    private function createEnvFile(string $content): string
    {
        $path = $this->tempDir . '/.env';
        file_put_contents($path, $content);

        return $path;
    }
}
