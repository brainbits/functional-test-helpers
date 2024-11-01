<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\SevenZipContents;

use RuntimeException;

use function sprintf;

final class InvalidArchive extends RuntimeException
{
    public static function notAFile(mixed $path): self
    {
        return new self(sprintf('Path %s is not valid', $path));
    }
}
