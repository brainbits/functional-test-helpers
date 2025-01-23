<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoMatchingMockRequest;
use SplObjectStorage;

use function count;
use function current;
use function krsort;

final class MockRequestResolver
{
    public function __invoke(
        MockRequestBuilderCollection $requestBuilders,
        RealRequest $realRequest,
    ): MockRequestBuilder {
        $scoredMatchResults = [];

        if (!count($requestBuilders)) {
            throw NoMatchingMockRequest::noBuilders($realRequest);
        }

        $builders = new SplObjectStorage();
        foreach ($requestBuilders as $requestBuilder) {
            $matchResult = ($requestBuilder->getMatcher())($realRequest);
            $scoredMatchResults[$matchResult->getScore()][] = $matchResult;
            $builders[$matchResult] = $requestBuilder;
        }

        krsort($scoredMatchResults);

        $missedMatchResults = [];
        if ($scoredMatchResults[0] ?? false) {
            $missedMatchResults = $scoredMatchResults[0];
            unset($scoredMatchResults[0]);
        }

        if (count($scoredMatchResults) === 0) {
            throw NoMatchingMockRequest::fromResults($realRequest, $missedMatchResults);
        }

        foreach ($scoredMatchResults as $matchResults) {
            foreach ($matchResults as $matchResult) {
                if ($builders[$matchResult]->hasNextResponse()) {
                    return $builders[$matchResult];
                }
            }
        }

        return $builders[current(current($scoredMatchResults))];
    }
}
