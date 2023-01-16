<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\ZipContents;

use Brainbits\FunctionalTestHelpers\ZipContents\ZipContents;
use PHPUnit\Framework\TestCase;

/** @covers \Brainbits\FunctionalTestHelpers\ZipContents\FileInfo */
final class FileInfoTest extends TestCase
{
    private const FILE = __DIR__ . '/../files/test.zip';

    public function testZipFile(): void
    {
        $zipContents = new ZipContents();
        $zipFile = $zipContents->readFile(self::FILE);
        $file = $zipFile->getFile('my-file.txt');

        self::assertSame('my-file.txt', $file->getPath());
        self::assertSame(7, $file->getSize());
        self::assertSame(7, $file->getCompressedSize());
        self::assertSame(0, $file->getCompression());
        self::assertSame('stored (no compression)', $file->getCompressionAsString());
        self::assertSame(2989266759, $file->getCrc());
        self::assertSame('b22c9747', $file->getCrcAsHex());
        self::assertSame('2020-07-24 14:00:02', $file->getLastModified()->format('Y-m-d H:i:s'));
        self::assertNull($file->getComment());
        self::assertFalse($file->isDir());
    }
}
