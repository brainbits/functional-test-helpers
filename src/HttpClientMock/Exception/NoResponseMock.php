<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use RuntimeException;

use function sprintf;

use const PHP_EOL;

final class NoResponseMock extends RuntimeException implements HttpClientMockException
{
    public static function noResponseAdded(): self
    {
        return new self('No response configured');
    }

    public static function allResponsesProcessed(): self
    {
        return new self('All responses have already been processed');
    }

    public static function withRequest(self $decorated, MockRequestBuilder $request): self
    {
        $message = sprintf('%s for:%s%s%s', $decorated->getMessage(), PHP_EOL, $request, PHP_EOL);

        return new self($message, $decorated->getCode(), $decorated->getPrevious());
    }
}
