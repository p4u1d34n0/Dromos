<?php

declare(strict_types=1);

namespace Dromos\Tests\Support;

/**
 * Gives a test a private temporary directory, removed afterwards.
 */
trait TemporaryDirectory
{
    private ?string $temporaryDirectory = null;

    protected function temporaryDirectory(): string
    {
        if ($this->temporaryDirectory === null) {
            $this->temporaryDirectory = sys_get_temp_dir() . '/dromos_test_' . bin2hex(random_bytes(6));
            mkdir($this->temporaryDirectory, 0700, true);
        }
        return $this->temporaryDirectory;
    }

    protected function removeTemporaryDirectory(): void
    {
        if ($this->temporaryDirectory === null || !is_dir($this->temporaryDirectory)) {
            return;
        }
        chmod($this->temporaryDirectory, 0700);
        foreach (glob($this->temporaryDirectory . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->temporaryDirectory);
        $this->temporaryDirectory = null;
    }
}
