<?php

declare(strict_types=1);

namespace Dromos\Tests\Http;

use Dromos\Http\Message\FileMoverInterface;
use Dromos\Http\Message\Stream;
use Dromos\Http\Message\StreamInterface;
use Dromos\Http\Message\UploadedFile;
use Dromos\Tests\Support\TemporaryDirectory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(UploadedFile::class)]
final class UploadedFileTest extends TestCase
{
    use TemporaryDirectory;

    protected function tearDown(): void
    {
        $this->removeTemporaryDirectory();
    }

    private static function upload(string $content = 'file contents', int $error = UPLOAD_ERR_OK, ?FileMoverInterface $mover = null): UploadedFile
    {
        return new UploadedFile(new Stream($content), strlen($content), $error, 'photo.jpg', 'image/jpeg', $mover);
    }

    /** A mover that records what it was asked to move, and where. */
    private static function spyMover(): FileMoverInterface
    {
        return new class implements FileMoverInterface {
            /** @var list<array{StreamInterface, string}> */
            public array $moves = [];

            public function moveTo(StreamInterface $stream, string $targetPath): void
            {
                $this->moves[] = [$stream, $targetPath];
            }
        };
    }

    #[Test]
    public function it_exposes_what_the_client_sent(): void
    {
        $file = self::upload('12345');

        $this->assertSame(5, $file->getSize());
        $this->assertSame(UPLOAD_ERR_OK, $file->getError());
        $this->assertSame('photo.jpg', $file->getClientFilename());
        $this->assertSame('image/jpeg', $file->getClientMediaType());
        $this->assertSame('12345', (string) $file->getStream());
    }

    #[Test]
    public function it_moves_the_contents_to_the_target_with_the_default_mover(): void
    {
        $target = $this->temporaryDirectory() . '/photo.jpg';

        self::upload('jpeg bytes')->moveTo($target);

        $this->assertSame('jpeg bytes', file_get_contents($target));
    }

    #[Test]
    public function it_moves_through_the_given_mover(): void
    {
        $stream = new Stream('data');
        $mover = self::spyMover();
        $file = new UploadedFile($stream, 4, UPLOAD_ERR_OK, null, null, $mover);

        $file->moveTo('/uploads/a.txt');

        $this->assertCount(1, $mover->moves);
        $this->assertSame($stream, $mover->moves[0][0]);
        $this->assertSame('/uploads/a.txt', $mover->moves[0][1]);
    }

    #[Test]
    public function it_accepts_0_as_a_target_path(): void
    {
        $directory = $this->temporaryDirectory();
        $previous = (string) getcwd();
        chdir($directory);
        try {
            self::upload('zero')->moveTo('0');
        } finally {
            chdir($previous);
        }

        $this->assertSame('zero', file_get_contents("$directory/0"));
    }

    #[Test]
    public function it_refuses_an_empty_target_path(): void
    {
        $mover = self::spyMover();

        try {
            self::upload(mover: $mover)->moveTo('');
            $this->fail('An empty target should be refused.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $mover->moves);
        }
    }

    #[Test]
    public function it_refuses_a_second_move_and_the_stream_after_a_move(): void
    {
        $file = self::upload(mover: self::spyMover());
        $file->moveTo('/uploads/a.txt');

        try {
            $file->moveTo('/uploads/b.txt');
            $this->fail('A second move should be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already been moved', $e->getMessage());
        }

        $this->expectException(RuntimeException::class);
        $file->getStream();
    }

    #[Test]
    public function it_stays_unmoved_when_the_mover_fails(): void
    {
        $failing = new class implements FileMoverInterface {
            public int $calls = 0;

            public function moveTo(StreamInterface $stream, string $targetPath): void
            {
                if ($this->calls++ === 0) {
                    throw new RuntimeException('disk full');
                }
            }
        };
        $file = self::upload(mover: $failing);

        try {
            $file->moveTo('/uploads/a.txt');
            $this->fail('The mover failure should propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('disk full', $e->getMessage());
        }

        $file->moveTo('/uploads/a.txt'); // a retry is allowed
        $this->assertSame(2, $failing->calls);
    }

    /** @return array<string, array{int}> */
    public static function failedUploads(): array
    {
        return [
            'ini size'   => [UPLOAD_ERR_INI_SIZE],
            'form size'  => [UPLOAD_ERR_FORM_SIZE],
            'partial'    => [UPLOAD_ERR_PARTIAL],
            'no file'    => [UPLOAD_ERR_NO_FILE],
            'no tmp dir' => [UPLOAD_ERR_NO_TMP_DIR],
            'cant write' => [UPLOAD_ERR_CANT_WRITE],
            'extension'  => [UPLOAD_ERR_EXTENSION],
        ];
    }

    #[Test]
    #[DataProvider('failedUploads')]
    public function it_has_no_stream_for_a_failed_upload(int $error): void
    {
        $file = self::upload(error: $error);

        $this->assertSame($error, $file->getError());
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("error code $error");

        $file->getStream();
    }

    #[Test]
    #[DataProvider('failedUploads')]
    public function it_refuses_to_move_a_failed_upload(int $error): void
    {
        $target = $this->temporaryDirectory() . '/upload.txt';

        try {
            self::upload(error: $error)->moveTo($target);
            $this->fail('A failed upload should not be moved.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("error code $error", $e->getMessage());
        }

        $this->assertFileDoesNotExist($target);
    }

    /** @return array<string, array{int}> */
    public static function invalidErrorCodes(): array
    {
        return [
            'unused code 5' => [5],
            'unknown 9'     => [9],
            'unknown 99'    => [99],
            'negative'      => [-1],
        ];
    }

    #[Test]
    #[DataProvider('invalidErrorCodes')]
    public function it_rejects_an_error_code_php_does_not_define(int $error): void
    {
        $this->expectException(InvalidArgumentException::class);

        self::upload(error: $error);
    }
}
