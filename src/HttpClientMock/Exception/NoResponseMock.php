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

    public static function unexpectedCall(MockRequestBuilder $requestBuilder, int $expected, int $actual): self
    {
        return new self(sprintf(
            'Mock request %s was expected to be called %d times, but was called %d times.',
            $requestBuilder->name !== null ? sprintf('"%s"', $requestBuilder->name) : sprintf('[%s]', $requestBuilder),
            $expected,
            $actual,
        ));
    }

    public static function withRequest(self $decorated, MockRequestBuilder $requestBuilder): self
    {
        $message = sprintf(
            '%s for:%s%s%s',
            $decorated->getMessage(),
            PHP_EOL,
            $requestBuilder,
            PHP_EOL,
        );

        return new self($message, $decorated->getCode(), $decorated->getPrevious());
    }
}
