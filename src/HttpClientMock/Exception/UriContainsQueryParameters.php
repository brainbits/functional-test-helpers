<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use RuntimeException;

use function sprintf;

final class UriContainsQueryParameters extends RuntimeException implements HttpClientMockException
{
    public static function fromUri(string $uri): self
    {
        return new self(sprintf('Given uri %s conts query parameters, use queryParam() calls instead.', $uri));
    }
}
