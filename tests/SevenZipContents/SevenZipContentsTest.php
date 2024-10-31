<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\SevenZipContents;

use Brainbits\FunctionalTestHelpers\SevenZipContents\Archive7z;
use Brainbits\FunctionalTestHelpers\SevenZipContents\FileInfo;
use Brainbits\FunctionalTestHelpers\SevenZipContents\InvalidArchive;
use Brainbits\FunctionalTestHelpers\SevenZipContents\ZipContents;
use Brainbits\FunctionalTestHelpers\SevenZipContents\ZipInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;
use function sprintf;

#[CoversClass(Archive7z::class)]
#[CoversClass(FileInfo::class)]
#[CoversClass(InvalidArchive::class)]
#[CoversClass(ZipInfo::class)]
#[CoversClass(ZipContents::class)]
final class SevenZipContentsTest extends TestCase
{
    private const FILE = __DIR__ . '/../files/test.7z';

    public function testItNeedsFile(): void
    {
        $this->expectException(InvalidArchive::class);
        $this->expectExceptionMessageMatches('#Path .*/tests/SevenZipContents/foo is not valid#');

        $zipContents = new ZipContents();
        $zipContents->readFile(sprintf('%s/foo', __DIR__));
    }

    public function testItReadsFile(): void
    {
        $zipContents = new ZipContents();
        $zipInfo = $zipContents->readFile(self::FILE);

        self::assertSame(141, $zipInfo->getSize());

        self::assertCount(1, $zipInfo);
        self::assertCount(1, $zipInfo->getFiles());
        self::assertCount(1, iterator_to_array($zipInfo));
    }

    public function testItCreatesZipInfo(): void
    {
        $zipContents = new ZipContents();
        $zipInfo = $zipContents->readFile(self::FILE);

        self::assertTrue($zipInfo->hasFile('my-file.txt'));
        self::assertNotNull($zipInfo->getFile('my-file.txt'));
        self::assertNull($zipInfo->getFile('not-existing-file.txt'));
    }

    public function testItCreatesFileInfo(): void
    {
        $zipContents = new ZipContents();
        $zipInfo = $zipContents->readFile(self::FILE);
        $fileInfo = $zipInfo->getFile('my-file.txt');
        self::assertNotNull($fileInfo);

        self::assertSame('my-file.txt', $fileInfo->getPath());
        self::assertSame(7, $fileInfo->getSize());
        self::assertSame(11, $fileInfo->getCompressedSize());
        self::assertSame(157, $fileInfo->getCompression());
        self::assertSame('B22C9747', $fileInfo->getCrc());
        self::assertFalse($fileInfo->isDir());
        self::assertSame('2020-07-24 12:00:02', $fileInfo->getLastModified()->format('Y-m-d H:i:s'));
        self::assertNull($fileInfo->getComment());
    }
}
