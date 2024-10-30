<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\ZipContents;

use Brainbits\FunctionalTestHelpers\ZipContents\ZipContentsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

use function Safe\filesize;
use function Safe\fopen;

#[CoversClass(ZipContentsTrait::class)]
final class ZipContentsTraitTest extends TestCase
{
    use ZipContentsTrait;

    private const FILE = __DIR__ . '/../files/test.zip';

    public function testAssertZipHasSizeForZipFileFails(): void
    {
        $zip = self::readZipFile(self::FILE);

        try {
            self::assertZipHasSize(99, $zip, 'assertZipHasSizeFailed');

            self::fail('ExpectationFailedException was not thrown.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('assertZipHasSizeFailed', $e->getMessage());
        }
    }

    public function testAssertZipHasSizeForZipFile(): void
    {
        $zip = self::readZipFile(self::FILE);

        self::assertZipHasSize(201, $zip);
    }

    public function testAssertZipHasSizeForZipStream(): void
    {
        $zip = self::readZipStream(fopen(self::FILE, 'rb'), filesize(self::FILE));

        self::assertZipHasSize(201, $zip);
    }

    public function testAssertZipHasNumberOfFilesForZipFileFails(): void
    {
        $zip = self::readZipFile(self::FILE);

        try {
            self::assertZipHasNumberOfFiles(99, $zip, 'assertZipHasNumberOfFilesFailed');

            self::fail('ExpectationFailedException was not thrown.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('assertZipHasNumberOfFilesFailed', $e->getMessage());
        }
    }

    public function testAssertZipHasNumberOfFilesForZipFile(): void
    {
        $zip = self::readZipFile(self::FILE);

        self::assertZipHasNumberOfFiles(1, $zip);
    }

    public function testAssertZipHasNumberOfFilesForZipStream(): void
    {
        $zip = self::readZipStream(fopen(self::FILE, 'rb'), filesize(self::FILE));

        self::assertZipHasNumberOfFiles(1, $zip);
    }

    public function testAssertZipHasFileForZipFileFails(): void
    {
        $zip = self::readZipFile(self::FILE);

        try {
            self::assertZipHasFile('foo.txt', $zip, 'assertZipHasFileFailed');

            self::fail('ExpectationFailedException was not thrown.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('assertZipHasFileFailed', $e->getMessage());
        }
    }

    public function testAssertZipHasFileForZipFile(): void
    {
        $zip = self::readZipFile(self::FILE);

        self::assertZipHasFile('my-file.txt', $zip);
    }

    public function testAssertZipHasFileForZipStream(): void
    {
        $zip = self::readZipStream(fopen(self::FILE, 'rb'), filesize(self::FILE));

        self::assertZipHasFile('my-file.txt', $zip);
    }

    public function testAssertZipHasFileWithSizeForZipFileFails(): void
    {
        $zip = self::readZipFile(self::FILE);

        try {
            self::assertZipHasFileWithSize('foo.txt', 7, $zip, 'assertZipHasFileWithSize');

            self::fail('ExpectationFailedException was not thrown.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('assertZipHasFileWithSize', $e->getMessage());
        }
    }

    public function testAssertZipHasFileWithSizeForZipFile(): void
    {
        $zip = self::readZipFile(self::FILE);

        self::assertZipHasFileWithSize('my-file.txt', 7, $zip);
    }

    public function testAssertZipHasFileWithSizeForZipStream(): void
    {
        $zip = self::readZipStream(fopen(self::FILE, 'rb'), filesize(self::FILE));

        self::assertZipHasFileWithSize('my-file.txt', 7, $zip);
    }

    public function testAssertZipHasFileWithCrcForZipFileFails(): void
    {
        $zip = self::readZipFile(self::FILE);

        try {
            self::assertZipHasFileWithCrc('foo.txt', 'b22c9747', $zip, 'assertZipHasFileWithCrc');

            self::fail('ExpectationFailedException was not thrown.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('assertZipHasFileWithCrc', $e->getMessage());
        }
    }

    public function testAssertZipHasFileWithCrcForZipFile(): void
    {
        $zip = self::readZipFile(self::FILE);

        self::assertZipHasFileWithCrc('my-file.txt', 'b22c9747', $zip);
    }

    public function testAssertZipHasFileWithCrcForZipStream(): void
    {
        $zip = self::readZipStream(fopen(self::FILE, 'rb'), filesize(self::FILE));

        self::assertZipHasFileWithCrc('my-file.txt', 'b22c9747', $zip);
    }
}
