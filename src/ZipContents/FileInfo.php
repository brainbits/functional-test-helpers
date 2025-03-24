<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\ZipContents;

use DateTimeImmutable;
use DateTimeZone;

use function array_key_exists;
use function array_pop;
use function array_push;
use function dechex;
use function explode;
use function implode;
use function str_replace;
use function trim;

final class FileInfo
{
    private const int COMPRESSION_STORED = 0;
    private const int COMPRESSION_SHRUNK = 1;
    private const int COMPRESSION_REDUCED_FACTOR_1 = 2;
    private const int COMPRESSION_REDUCED_FACTOR_2 = 3;
    private const int COMPRESSION_REDUCED_FACTOR_3 = 4;
    private const int COMPRESSION_REDUCED_FACTOR_4 = 5;
    private const int COMPRESSION_IMPLODED = 6;
    private const int COMPRESSION_DEFLATED = 8;
    private const int COMPRESSION_DEFLATE64 = 9;
    private const int COMPRESSION_PKZIP = 10;
    private const int COMPRESSION_BZIP2 = 12;
    private const int COMPRESSION_LZMA = 14;
    private const int COMPRESSION_CMPSC = 16;
    private const int COMPRESSION_TERSE = 18;
    private const int COMPRESSION_LZ77 = 19;
    private const int COMPRESSION_ZSTD = 93;
    private const int COMPRESSION_MP3 = 94;
    private const int COMPRESSION_XZ = 95;
    private const int COMPRESSION_JPEG = 96;
    private const int COMPRESSION_WAVPACK = 97;
    private const int COMPRESSION_PPMD = 98;
    private const int COMPRESSION_AEX = 99;

    private const array COMPRESSION_MAP = [
        self::COMPRESSION_STORED => 'stored (no compression)',
        self::COMPRESSION_SHRUNK => 'Shrunk',
        self::COMPRESSION_REDUCED_FACTOR_1 => 'Reduced with compression factor 1',
        self::COMPRESSION_REDUCED_FACTOR_2 => 'Reduced with compression factor 2',
        self::COMPRESSION_REDUCED_FACTOR_3 => 'Reduced with compression factor 3',
        self::COMPRESSION_REDUCED_FACTOR_4 => 'Reduced with compression factor 4',
        self::COMPRESSION_IMPLODED => 'Imploded',
        self::COMPRESSION_DEFLATED => 'Deflated',
        self::COMPRESSION_DEFLATE64 => 'Enhanced Deflating using Deflate64(tm)',
        self::COMPRESSION_PKZIP => 'PKWARE Data Compression Library Imploding',
        self::COMPRESSION_BZIP2 => 'BZIP2',
        self::COMPRESSION_LZMA => 'LZMA',
        self::COMPRESSION_CMPSC => 'IBM z/OS CMPSC',
        self::COMPRESSION_TERSE => 'IBM TERSE',
        self::COMPRESSION_LZ77 => 'IBM LZ77 z',
        self::COMPRESSION_ZSTD => 'Zstandard',
        self::COMPRESSION_MP3 => 'MP3',
        self::COMPRESSION_XZ => 'XZ',
        self::COMPRESSION_JPEG => 'JPEG',
        self::COMPRESSION_WAVPACK => 'WavPack',
        self::COMPRESSION_PPMD => 'PPMd',
        self::COMPRESSION_AEX => 'AE-x',
    ];

    private string $path;
    private DateTimeImmutable $lastModified;

    /**
     * initialize dynamic defaults
     *
     * @param string $path The path of the file, can also be set later through setPath()
     */
    public function __construct(
        string $path,
        private int $size,
        private int $compressedSize,
        private int $compression,
        int $lastModified,
        private int $crc,
        private string|null $comment,
        private bool $isDir,
    ) {
        $this->path = $this->cleanPath($path);
        $this->lastModified = DateTimeImmutable::createFromFormat(
            'U',
            (string) $lastModified,
            new DateTimeZone('UTC'),
        );
    }

    /** @param mixed[] $header */
    public static function fromCentralFileHeader(array $header): self
    {
        $path = null;
        // phpcs:ignore Generic.Files.LineLength.TooLong
        if (array_key_exists('extradata', $header) && array_key_exists('utf8path', $header['extradata']) && $header['extradata']['utf8path']) {
            $path = $header['extradata']['utf8path'];
        } elseif ($header['filename']) {
            $path = $header['filename'];
        }

        $comment = null;
        // phpcs:ignore Generic.Files.LineLength.TooLong
        if (array_key_exists('extradata', $header) && array_key_exists('utf8comment', $header['extradata']) && $header['extradata']['utf8comment']) {
            $comment = $header['extradata']['utf8comment'];
        } elseif ($header['comment']) {
            $comment = $header['comment'];
        }

        return new FileInfo(
            $path,
            $header['size'],
            $header['compressed_size'],
            $header['compression'],
            $header['mtime'],
            $header['crc'],
            $comment,
            $header['external'] === 0x41FF0010 || $header['external'] === 16,
        );
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

    public function getCompressionAsString(): string
    {
        return self::COMPRESSION_MAP[$this->compression];
    }

    public function getLastModified(): DateTimeImmutable
    {
        return $this->lastModified;
    }

    public function getCrc(): int
    {
        return $this->crc;
    }

    public function getCrcAsHex(): string
    {
        return dechex($this->crc);
    }

    public function getComment(): string|null
    {
        return $this->comment;
    }

    public function isDir(): bool
    {
        return $this->isDir;
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
