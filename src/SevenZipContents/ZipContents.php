<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\SevenZipContents;

use function is_file;

final class ZipContents
{
    public function readFile(string $file): ZipInfo
    {
        if (!is_file($file)) {
            throw InvalidArchive::notAFile($file);
        }

        $archive = new Archive7z($file);
        $info = $archive->getInfo();

        $fileInfos = [];
        foreach ($archive->getEntries() as $entry) {
            $path = $entry->getPath();

            $fileInfos[$path] = new FileInfo(
                $path,
                (int) $entry->getSize(),
                (int) $entry->getPackedSize(),
                (int) ((int) ($entry->getPackedSize()) * 100 / (int) $entry->getSize()),
                $entry->getModified(),
                $entry->getCrc(),
                $entry->getComment(),
                $entry->isDirectory(),
            );
        }

        return new ZipInfo($info->getPhysicalSize(), $fileInfos);
    }
}
