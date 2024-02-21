<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\ZipContents;

use RuntimeException;

use function gettype;
use function sprintf;

final class InvalidArchive extends RuntimeException
{
    public static function notAFile(mixed $path): self
    {
        return new self(sprintf('Path %s is not valid', $path));
    }

    public static function notAStream(mixed $stream): self
    {
        return new self(sprintf('Valid stream is required, %s given', gettype($stream)));
    }

    public static function zeroSize(): self
    {
        return new self('ZIPs with size zero are not supported');
    }
}
