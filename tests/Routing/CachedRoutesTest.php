<?php

declare(strict_types=1);

namespace Dromos\Tests\Routing;

use Dromos\Routing\CachedRoutes;
use Dromos\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CachedRoutes::class)]
final class CachedRoutesTest extends TestCase
{
    use TemporaryDirectory;

    private string $file;

    protected function setUp(): void
    {
        $this->file = $this->temporaryDirectory() . '/routes.php';
        file_put_contents($this->file, '<?php // one');
        touch($this->file, 1_700_000_000);
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    #[Test]
    public function test_a_missing_file_has_no_signature(): void
    {
        $this->assertNull(CachedRoutes::signatureOfFile($this->temporaryDirectory() . '/nope.php'));
    }

    #[Test]
    public function test_an_unchanged_file_keeps_its_signature(): void
    {
        $this->assertSame(CachedRoutes::signatureOfFile($this->file), CachedRoutes::signatureOfFile($this->file));
    }

    #[Test]
    public function test_the_signature_changes_with_the_modification_time(): void
    {
        $before = CachedRoutes::signatureOfFile($this->file);
        touch($this->file, 1_700_000_001);
        $this->nextRequest();

        $this->assertNotSame($before, CachedRoutes::signatureOfFile($this->file));
    }

    #[Test]
    public function test_the_signature_changes_with_the_size_even_at_the_same_time(): void
    {
        $before = CachedRoutes::signatureOfFile($this->file);
        file_put_contents($this->file, '<?php // longer');
        touch($this->file, 1_700_000_000);
        $this->nextRequest();

        $this->assertNotSame($before, CachedRoutes::signatureOfFile($this->file));
    }

    #[Test]
    public function test_the_signature_changes_when_the_file_is_replaced_with_the_same_size_and_time(): void
    {
        $before = CachedRoutes::signatureOfFile($this->file);
        // A deploy that swaps the file in: same size and time, new inode
        $replacement = $this->temporaryDirectory() . '/routes.new';
        file_put_contents($replacement, '<?php // two');
        touch($replacement, 1_700_000_000);
        rename($replacement, $this->file);
        $this->nextRequest();

        $this->assertNotSame($before, CachedRoutes::signatureOfFile($this->file));
    }

    #[Test]
    public function test_each_file_has_its_own_signature(): void
    {
        $other = $this->temporaryDirectory() . '/other.php';
        copy($this->file, $other);
        touch($other, 1_700_000_000);

        $this->assertNotSame(CachedRoutes::signatureOfFile($this->file), CachedRoutes::signatureOfFile($other));
    }

    /**
     * PHP keeps the last stat() result until the request ends, so a change
     * made during a request is seen by the next one. Clearing it starts that
     * next request.
     */
    private function nextRequest(): void
    {
        clearstatcache();
    }
}
