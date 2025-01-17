<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\SevenZipContents;

use Archive7z\Exception;
use Brainbits\FunctionalTestHelpers\SevenZipContents\SevenZipContents;
use Brainbits\FunctionalTestHelpers\SevenZipContents\SevenZipContentsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

#[CoversClass(SevenZipContentsTrait::class)]
final class SevenZipContentsTraitTest extends TestCase
{
    use SevenZipContentsTrait;

    private const FILE = __DIR__ . '/../files/test.7z';

    protected function setUp(): void
    {
        try {
            $zipContents = new SevenZipContents();
            $zipContents->readFile(self::FILE);
        } catch (Exception $e) {
            if ($e->getMessage() === 'Binary of 7-zip is not available') {
                $this->markTestSkipped('Binary of 7-zip is not available');
            }
        }
    }

    public function testAssertZipHasSizeForZipFileFails(): void
    {
        $zip = self::read7zFile(self::FILE);

        try {
            self::assert7zHasSize(99, $zip, 'assert7zHasSizeFailed');

            self::fail('ExpectationFailedException was not thrown.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('assert7zHasSizeFailed', $e->getMessage());
        }
    }

    public function testAssertZipHasSizeForZipFile(): void
    {
        $zip = self::read7zFile(self::FILE);

        self::assert7zHasSize(141, $zip);
    }

    public function testAssertZipHasNumberOfFilesForZipFileFails(): void
    {
        $zip = self::read7zFile(self::FILE);

        try {
            self::assert7zHasNumberOfFiles(99, $zip, 'assert7zHasNumberOfFilesFailed');

            self::fail('ExpectationFailedException was not thrown.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('assert7zHasNumberOfFilesFailed', $e->getMessage());
        }
    }

    public function testAssertZipHasNumberOfFilesForZipFile(): void
    {
        $zip = self::read7zFile(self::FILE);

        self::assert7zHasNumberOfFiles(1, $zip);
    }

    public function testAssertZipHasFileForZipFileFails(): void
    {
        $zip = self::read7zFile(self::FILE);

        try {
            self::assert7zHasFile('foo.txt', $zip, 'assert7zHasFileFailed');

            self::fail('ExpectationFailedException was not thrown.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('assert7zHasFileFailed', $e->getMessage());
        }
    }

    public function testAssertZipHasFileForZipFile(): void
    {
        $zip = self::read7zFile(self::FILE);

        self::assert7zHasFile('my-file.txt', $zip);
    }

    public function testAssertZipHasFileWithSizeForZipFileFails(): void
    {
        $zip = self::read7zFile(self::FILE);

        try {
            self::assert7zHasFileWithSize('foo.txt', 7, $zip, 'assert7zHasFileWithSize');

            self::fail('ExpectationFailedException was not thrown.');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('assert7zHasFileWithSize', $e->getMessage());
        }
    }

    public function testAssertZipHasFileWithSizeForZipFile(): void
    {
        $zip = self::read7zFile(self::FILE);

        self::assert7zHasFileWithSize('my-file.txt', 7, $zip);
    }
}
