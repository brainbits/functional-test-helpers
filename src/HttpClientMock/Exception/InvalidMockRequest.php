<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use RuntimeException;

use function Safe\sprintf;

final class InvalidMockRequest extends RuntimeException
{
    public static function notXml(string $input): self
    {
        return new self(sprintf('No valid xml: %s', $input));
    }
}
