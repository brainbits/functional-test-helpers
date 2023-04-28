<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\ZipContents;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

/** @mixin TestCase */
trait ZipContentsTrait
{
    /** @param resource $stream */
    final protected static function readZipStream($stream, int $size): ZipInfo
    {
        $zipContents = new ZipContents();

        return $zipContents->readStream($stream, $size);
    }

    final protected static function readZipFile(string $path): ZipInfo
    {
        $zipContents = new ZipContents();

        return $zipContents->readFile($path);
    }

    final protected static function assertZipHasSize(int $expectedSize, ZipInfo $zip, string $message = ''): void
    {
        Assert::assertSame($expectedSize, $zip->getSize(), $message);
    }

    final protected static function assertZipHasNumberOfFiles(
        int $expectedNumberOfFiles,
        ZipInfo $zip,
        string $message = '',
    ): void {
        Assert::assertCount($expectedNumberOfFiles, $zip, $message);
    }

    final protected static function assertZipHasFile(string $expectedPath, ZipInfo $zip, string $message = ''): void
    {
        Assert::assertTrue($zip->hasFile($expectedPath), $message);
    }

    final protected static function assertZipHasFileWithSize(
        string $expectedPath,
        int $expectedSize,
        ZipInfo $zip,
        string $message = '',
    ): void {
        self::assertZipHasFile($expectedPath, $zip, $message);

        $file = $zip->getFile($expectedPath);

        Assert::assertSame($expectedSize, $file->getSize(), $message);
    }

    final protected static function assertZipHasFileWithCrc(
        string $expectedPath,
        string $expectedCrc,
        ZipInfo $zip,
        string $message = '',
    ): void {
        self::assertZipHasFile($expectedPath, $zip, $message);

        $file = $zip->getFile($expectedPath);

        Assert::assertSame($expectedCrc, $file->getCrcAsHex(), $message);
    }
}
