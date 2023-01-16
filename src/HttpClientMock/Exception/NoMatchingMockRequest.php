<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Exception;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatch;
use RuntimeException;

use function Safe\sprintf;

use const PHP_EOL;

final class NoMatchingMockRequest extends RuntimeException implements HttpClientMockException
{
    /** @param MockRequestMatch[] $matches */
    public static function fromMockRequest(MockRequestBuilder $request, array $matches): self
    {
        $message = sprintf('No matching mock request builder found for:%s%s%s', PHP_EOL, $request, PHP_EOL);

        if ($matches) {
            $message .= sprintf('%sReasons:%s', PHP_EOL, PHP_EOL);
            foreach ($matches as $match) {
                $message .= sprintf('- %s%s', $match->getReason(), PHP_EOL);
            }
        }

        return new self($message);
    }
}
