<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\SevenZipContents;

use Countable;
use Generator;
use IteratorAggregate;

use function array_key_exists;
use function array_values;
use function count;

/** @implements IteratorAggregate<string, FileInfo> */
final class ZipInfo implements Countable, IteratorAggregate
{
    /** @param FileInfo[] $files */
    public function __construct(private int $size, private array $files)
    {
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /** @return list<FileInfo> */
    public function getFiles(): array
    {
        return array_values($this->files);
    }

    public function hasFile(string $path): bool
    {
        return array_key_exists($path, $this->files);
    }

    public function getFile(string $path): FileInfo|null
    {
        if (!$this->hasFile($path)) {
            return null;
        }

        return $this->files[$path];
    }

    public function getIterator(): Generator
    {
        yield from $this->files;
    }

    public function count(): int
    {
        return count($this->files);
    }
}
