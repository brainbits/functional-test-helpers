<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\ZipContents;

use Brainbits\FunctionalTestHelpers\ZipContents\InvalidArchive;
use Brainbits\FunctionalTestHelpers\ZipContents\ZipContents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function Safe\filesize;
use function Safe\fopen;
use function sprintf;

#[CoversClass(ZipContents::class)]
final class ZipContentsTest extends TestCase
{
    private const FILE = __DIR__ . '/../files/test.zip';

    public function testItNeedsFile(): void
    {
        $this->expectException(InvalidArchive::class);
        $this->expectExceptionMessageMatches('#Path .*/tests/ZipContents/foo is not valid#');

        $zipContents = new ZipContents();
        $zipContents->readFile(sprintf('%s/foo', __DIR__));
    }

    public function testItNeedsStream(): void
    {
        $this->expectException(InvalidArchive::class);
        $this->expectExceptionMessage('Valid stream is required, string given');

        $zipContents = new ZipContents();
        $zipContents->readStream('foo', filesize(self::FILE)); // @phpstan-ignore-line
    }

    public function testItNeedsSize(): void
    {
        $this->expectException(InvalidArchive::class);
        $this->expectExceptionMessage('ZIPs with size zero are not supported');

        $zipContents = new ZipContents();
        $zipContents->readStream(fopen(self::FILE, 'rb+'), 0);
    }

    public function testItReadsFile(): void
    {
        $zipContents = new ZipContents();
        $info = $zipContents->readFile(self::FILE);

        self::assertCount(1, $info);
    }

    public function testItReadsStream(): void
    {
        $zipContents = new ZipContents();
        $info = $zipContents->readStream(fopen(self::FILE, 'rb+'), filesize(self::FILE));

        self::assertCount(1, $info);
    }
}
