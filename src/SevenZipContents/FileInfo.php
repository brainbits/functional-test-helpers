<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\SevenZipContents;

use DateTimeImmutable;
use DateTimeZone;

use function array_pop;
use function array_push;
use function explode;
use function implode;
use function str_replace;
use function substr;
use function trim;

final readonly class FileInfo
{
    private string $path;
    private DateTimeImmutable $lastModified;

    public function __construct(
        string $path,
        private int $size,
        private int $compressedSize,
        private int $compression,
        string $lastModified,
        private string $crc,
        private string|null $comment,
        private bool $isDir,
    ) {
        $this->path = $this->cleanPath($path);

        $utc = new DateTimeZone('UTC');

        $this->lastModified = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', substr($lastModified, 0, 19), $utc);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getSize(): int
    {
        if ($this->isDir) {
            return 0;
        }

        return $this->size;
    }

    public function getCompressedSize(): int
    {
        return $this->compressedSize;
    }

    public function getCompression(): int
    {
        return $this->compression;
    }

    public function getCrc(): string
    {
        return $this->crc;
    }

    public function getComment(): string|null
    {
        return $this->comment;
    }

    public function isDir(): bool
    {
        return $this->isDir;
    }

    public function getLastModified(): DateTimeImmutable
    {
        return $this->lastModified;
    }

    /**
     * Cleans up a path and removes relative parts, also strips leading slashes
     */
    private function cleanPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = explode('/', $path);
        $newpath = [];
        foreach ($path as $p) {
            if ($p === '' || $p === '.') {
                continue;
            }

            if ($p === '..') {
                array_pop($newpath);
                continue;
            }

            array_push($newpath, $p);
        }

        return trim(implode('/', $newpath), '/');
    }
}
