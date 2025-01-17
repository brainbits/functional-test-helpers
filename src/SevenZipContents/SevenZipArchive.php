<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\SevenZipContents;

use Archive7z\Archive7z;

use function escapeshellarg;
use function exec;
use function file_exists;
use function is_string;

final class SevenZipArchive extends Archive7z
{
    private const array EXECUTABLES = ['7z', '7zz', '7za'];

    private static string|null $binary7z = null;

    public function __construct(string $filename, float|null $timeout = 60.0)
    {
        parent::__construct($filename, self::getBinary7zFromPath(), $timeout);
    }

    private static function getBinary7zFromPath(): string
    {
        if (self::$binary7z) {
            return self::$binary7z;
        }

        $binary7z = null;
        foreach (self::EXECUTABLES as $executable) {
            $resultCode = 0;
            $binary7z = exec('which ' . escapeshellarg($executable), result_code: $resultCode); // @phpstan-ignore-line

            if ($resultCode === 0 && is_string($binary7z) && $binary7z !== '' && file_exists($binary7z)) {
                break;
            }
        }

        if (!$binary7z) {
            $binary7z = null;
        }

        self::$binary7z = self::makeBinary7z($binary7z);

        return self::$binary7z;
    }
}
