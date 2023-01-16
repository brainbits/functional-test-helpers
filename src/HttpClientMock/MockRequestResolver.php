<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoMatchingMockRequest;

use function array_pop;
use function count;

final class MockRequestResolver
{
    private MockRequestMatcher $matcher;

    public function __construct()
    {
        $this->matcher = new MockRequestMatcher();
    }

    /** @param mixed[] $options */
    public function __invoke(
        MockRequestBuilderCollection $requestBuilders,
        MockRequestBuilder $realRequest,
    ): MockRequestBuilder {
        $matches = [];
        $bestScore = null;
        $bestMatchingRequestBuilders = [];

        foreach ($requestBuilders as $requestBuilder) {
            $matches[] = $match = ($this->matcher)($requestBuilder, $realRequest);

            if ($match->isMismatch() || ($bestScore !== null && $match->getScore() < $bestScore)) {
                continue;
            }

            if ($bestScore === null || $match->getScore() > $bestScore) {
                $bestMatchingRequestBuilders = [];
                $bestScore = $match->getScore();
            }

            $bestMatchingRequestBuilders[] = $requestBuilder;
        }

        if (count($bestMatchingRequestBuilders) === 0) {
            throw NoMatchingMockRequest::fromMockRequest($realRequest, $matches);
        }

        foreach ($bestMatchingRequestBuilders as $requestBuilder) {
            if ($requestBuilder->hasNextResponse()) {
                return $requestBuilder;
            }
        }

        return array_pop($bestMatchingRequestBuilders);
    }
}
