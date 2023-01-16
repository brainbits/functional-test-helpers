<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\ZipContents;

use Brainbits\FunctionalTestHelpers\ZipContents\FileInfo;
use Brainbits\FunctionalTestHelpers\ZipContents\ZipContents;
use PHPUnit\Framework\TestCase;

/** @covers \Brainbits\FunctionalTestHelpers\ZipContents\FileInfo */
final class ZipInfoTest extends TestCase
{
    private const FILE = __DIR__ . '/../files/test.zip';

    public function testZipFile(): void
    {
        $zipContents = new ZipContents();
        $zipFile = $zipContents->readFile(self::FILE);

        self::assertSame(201, $zipFile->getSize());
        self::assertSame('this is a test comment', $zipFile->getComment());
        self::assertCount(1, $zipFile);
        $this->assertContainsOnlyInstancesOf(FileInfo::class, $zipFile->getFiles());
    }
}
