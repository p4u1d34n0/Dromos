<?php

declare(strict_types=1);

namespace Dromos\Tests\Env;

use Dromos\Env\EnvLoader;
use Dromos\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvLoader::class)]
final class EnvLoaderTest extends TestCase
{
    use TemporaryDirectory;

    /** @var array<string, mixed> */
    private array $env;

    protected function setUp(): void
    {
        $this->env = $_ENV;
        EnvLoader::reset();
    }

    protected function tearDown(): void
    {
        foreach (array_diff_key($_ENV, $this->env) as $name => $_) {
            if ($name !== '') {
                putenv((string) $name);
            }
        }
        $_ENV = $this->env;
        EnvLoader::reset();
        $this->removeTemporaryDirectory();
    }

    private function envFile(string $contents, string $name = '.env'): string
    {
        $path = $this->temporaryDirectory() . '/' . $name;
        file_put_contents($path, $contents);
        return $path;
    }

    #[Test]
    public function it_loads_variables_into_env_and_getenv(): void
    {
        EnvLoader::load($this->envFile("DROMOS_T_HOST=db.local\nDROMOS_T_PORT = 5432 \n"));

        $this->assertSame('db.local', $_ENV['DROMOS_T_HOST']);
        $this->assertSame('5432', $_ENV['DROMOS_T_PORT']);
        $this->assertSame('db.local', getenv('DROMOS_T_HOST'));
    }

    #[Test]
    public function it_skips_comments_blank_and_malformed_lines(): void
    {
        EnvLoader::load($this->envFile(
            "# DROMOS_T_COMMENTED=1\n   # DROMOS_T_INDENTED=1\n\n   \nDROMOS_T_NO_EQUALS\nDROMOS_T_KEPT=yes\n"
        ));

        $added = array_diff_key($_ENV, $this->env);
        $this->assertSame(['DROMOS_T_KEPT' => 'yes'], $added);
    }

    #[Test]
    public function a_line_without_a_name_sets_nothing(): void
    {
        EnvLoader::load($this->envFile("=orphan\n  = spaced\nDROMOS_T_AFTER=1\n"));

        $this->assertArrayNotHasKey('', $_ENV);
        $this->assertSame('1', $_ENV['DROMOS_T_AFTER']);
    }

    #[Test]
    public function only_the_first_equals_separates_name_and_value(): void
    {
        EnvLoader::load($this->envFile("DROMOS_T_DSN=mysql:host=db;port=3306\n"));

        $this->assertSame('mysql:host=db;port=3306', $_ENV['DROMOS_T_DSN']);
    }

    #[Test]
    public function it_never_overwrites_a_variable_that_is_already_set(): void
    {
        $_ENV['DROMOS_T_SET'] = 'from-server';

        EnvLoader::load($this->envFile("DROMOS_T_SET=from-file\n"));

        $this->assertSame('from-server', $_ENV['DROMOS_T_SET']);
        $this->assertFalse(getenv('DROMOS_T_SET'));
    }

    #[Test]
    public function a_missing_file_is_ignored_and_loads_once_it_exists(): void
    {
        $path = $this->temporaryDirectory() . '/.env';

        EnvLoader::load($path);
        $this->assertArrayNotHasKey('DROMOS_T_LATE', $_ENV);

        file_put_contents($path, "DROMOS_T_LATE=1\n");
        EnvLoader::load($path);
        $this->assertSame('1', $_ENV['DROMOS_T_LATE']);
    }

    #[Test]
    public function get_preserves_falsy_values_and_returns_any_default(): void
    {
        EnvLoader::load($this->envFile("DROMOS_T_ZERO=0\nDROMOS_T_EMPTY=\n"));

        $this->assertSame('0', EnvLoader::get('DROMOS_T_ZERO', 'default'));
        $this->assertSame('', EnvLoader::get('DROMOS_T_EMPTY', 'default'));
        $this->assertSame(30, EnvLoader::get('DROMOS_T_MISSING', 30));
        $this->assertNull(EnvLoader::get('DROMOS_T_MISSING'));
    }

    #[Test]
    public function get_falls_back_to_getenv(): void
    {
        putenv('DROMOS_T_PROCESS=from-process');
        try {
            $this->assertSame('from-process', EnvLoader::get('DROMOS_T_PROCESS'));
        } finally {
            putenv('DROMOS_T_PROCESS');
        }
    }

    #[Test]
    public function loading_the_same_file_again_does_not_reread_it(): void
    {
        $path = $this->envFile("DROMOS_T_FIRST=1\n");
        EnvLoader::load($path);

        file_put_contents($path, "DROMOS_T_FIRST=1\nDROMOS_T_SECOND=2\n");
        EnvLoader::load($path);
        // the same file through a different spelling of its path
        EnvLoader::load(dirname($path) . '/./.env');

        $this->assertArrayNotHasKey('DROMOS_T_SECOND', $_ENV);
    }

    #[Test]
    public function a_different_file_still_loads(): void
    {
        EnvLoader::load($this->envFile("DROMOS_T_BASE=1\n"));
        EnvLoader::load($this->envFile("DROMOS_T_LOCAL=1\n", '.env.local'));

        $this->assertSame('1', $_ENV['DROMOS_T_BASE']);
        $this->assertSame('1', $_ENV['DROMOS_T_LOCAL']);
    }

    #[Test]
    public function reset_lets_a_file_load_again(): void
    {
        $path = $this->envFile("DROMOS_T_FIRST=1\n");
        EnvLoader::load($path);
        file_put_contents($path, "DROMOS_T_SECOND=2\n");

        EnvLoader::reset();
        EnvLoader::load($path);

        $this->assertSame('2', $_ENV['DROMOS_T_SECOND']);
    }
}
