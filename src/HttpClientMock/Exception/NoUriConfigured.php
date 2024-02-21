<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use RuntimeException;

use function sprintf;

final class NoUriConfigured extends RuntimeException implements HttpClientMockException
{
    public static function fromTemplateKey(string $key): self
    {
        return new self(sprintf('No uri configured, can\'t replace template key {%s}', $key));
    }
}
