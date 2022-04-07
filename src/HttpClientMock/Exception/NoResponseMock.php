<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use RuntimeException;

final class NoResponseMock extends RuntimeException
{
    public static function noResponseAdded(): self
    {
        return new self('No response configured');
    }

    public static function allResponsesProcessed(): self
    {
        return new self('All responses have already been processed');
    }
}
