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

        // A mock with once(), never() or times() keeps its requests, neither an equally nor a less specific mock
        // may answer them, otherwise the call count check would miss the extra calls. Within the same score,
        // the order is: a mock with call count, calls and a response left, any other mock with call count,
        // any mock with a response.
        foreach ($scoredMatchResults as $matchResults) {
            $builderWithCallCount = null;
            $builderWithResponse = null;
            foreach ($matchResults as $matchResult) {
                $builder = $builders[$matchResult];

                if ($builder->getExpectedCallCount() === null) {
                    if ($builderWithResponse === null && $builder->hasNextResponse()) {
                        $builderWithResponse = $builder;
                    }

                    continue;
                }

                if ($builder->hasNextResponse() && count($builder->getCallStack()) < $builder->getExpectedCallCount()) {
                    return $builder;
                }

                $builderWithCallCount ??= $builder;
            }

            if ($builderWithCallCount !== null) {
                return $builderWithCallCount;
            }

            if ($builderWithResponse !== null) {
                return $builderWithResponse;
            }
        }

        return $builders[current(current($scoredMatchResults))];
    }
}
