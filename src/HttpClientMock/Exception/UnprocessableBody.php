<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use RuntimeException;

final class UnprocessableBody extends RuntimeException
{
    public static function create(): self
    {
        return new self('Body must be specified as string or callable');
    }
}
