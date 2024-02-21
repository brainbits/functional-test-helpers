<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use RuntimeException;

use function sprintf;

use const PHP_EOL;

final class AddMockResponseFailed extends RuntimeException implements HttpClientMockException
{
    public static function responseAlreadyAdded(): self
    {
        return new self('Response already added, add always not possible');
    }

    public static function singleResponseAlreadyAdded(): self
    {
        return new self('Single response already added, add not possible');
    }

    public static function withRequest(self $decorated, MockRequestBuilder $request): self
    {
        $message = sprintf('%s for:%s%s%s', $decorated->getMessage(), PHP_EOL, $request, PHP_EOL);

        return new self($message, $decorated->getCode(), $decorated->getPrevious());
    }
}
