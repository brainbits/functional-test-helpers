<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\SevenZipContents;

use Archive7z\Exception;
use Brainbits\FunctionalTestHelpers\SevenZipContents\InvalidArchive;
use Brainbits\FunctionalTestHelpers\SevenZipContents\SevenZipArchive;
use Brainbits\FunctionalTestHelpers\SevenZipContents\SevenZipContents;
use Brainbits\FunctionalTestHelpers\SevenZipContents\SevenZipFileInfo;
use Brainbits\FunctionalTestHelpers\SevenZipContents\SevenZipInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;
use function sprintf;

#[CoversClass(SevenZipArchive::class)]
#[CoversClass(SevenZipFileInfo::class)]
#[CoversClass(InvalidArchive::class)]
#[CoversClass(SevenZipInfo::class)]
#[CoversClass(SevenZipContents::class)]
final class SevenZipContentsTest extends TestCase
{
    private const FILE_7Z = __DIR__ . '/../files/test.7z';

    protected function setUp(): void
    {
        try {
            $zipContents = new SevenZipContents();
            $zipContents->readFile(self::FILE_7Z);
        } catch (Exception $e) {
            if ($e->getMessage() === 'Binary of 7-zip is not available') {
                $this->markTestSkipped('Binary of 7-zip is not available');
            }
        }
    }

    public function testItNeedsFile(): void
    {
        $this->expectException(InvalidArchive::class);
        $this->expectExceptionMessageMatches('#Path .*/tests/SevenZipContents/foo is not valid#');

        $zipContents = new SevenZipContents();
        $zipContents->readFile(sprintf('%s/foo', __DIR__));
    }

    public function testItReadsFile(): void
    {
        $zipContents = new SevenZipContents();
        $zipInfo = $zipContents->readFile(self::FILE_7Z);

        self::assertSame(141, $zipInfo->getSize());

        self::assertCount(1, $zipInfo);
        self::assertCount(1, $zipInfo->getFiles());
        self::assertCount(1, iterator_to_array($zipInfo));
    }

    public function testItCreatesZipInfo(): void
    {
        $zipContents = new SevenZipContents();
        $zipInfo = $zipContents->readFile(self::FILE_7Z);

        self::assertTrue($zipInfo->hasFile('my-file.txt'));
        self::assertNotNull($zipInfo->getFile('my-file.txt'));
        self::assertNull($zipInfo->getFile('not-existing-file.txt'));
    }

    public function testItCreatesFileInfo(): void
    {
        $zipContents = new SevenZipContents();
        $zipInfo = $zipContents->readFile(self::FILE_7Z);
        $fileInfo = $zipInfo->getFile('my-file.txt');
        self::assertNotNull($fileInfo);

        self::assertSame('my-file.txt', $fileInfo->getPath());
        self::assertSame(7, $fileInfo->getSize());
        self::assertSame(11, $fileInfo->getCompressedSize());
        self::assertSame(157, $fileInfo->getCompression());
        self::assertSame('B22C9747', $fileInfo->getCrc());
        self::assertFalse($fileInfo->isDir());
        self::assertSame('2020-07-24 12:00:02', $fileInfo->getLastModified()?->format('Y-m-d H:i:s'));
    }
}
