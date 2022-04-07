<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use RuntimeException;

final class AddMockResponseFailed extends RuntimeException
{
    public static function responseAlreadyAdded(): self
    {
        return new self('Response already added, add always not possible');
    }

    public static function singleResponseAlreadyAdded(): self
    {
        return new self('Single response already added, add not possible');
    }
}
