<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\CallbackMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Matcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MatchResult;

use function array_values;
use function ksort;

final readonly class MockRequestMatcher
{
    /** @param Matcher[] $matchers */
    public function __construct(private string|null $name, private array $matchers)
    {
    }

    public function __invoke(RealRequest $realRequest): MatchResult
    {
        $results = [];
        $callbackMatchers = [];
        $isMismatch = false;

        foreach (array_values($this->matchers) as $index => $matcher) {
            if ($matcher instanceof CallbackMatcher && $matcher->isCallback()) {
                $callbackMatchers[$index] = $matcher;

                continue;
            }

            $results[$index] = $matcher($realRequest);
            $isMismatch = $isMismatch || !$results[$index] instanceof Hit;
        }

        // Callbacks may contain assertions, so they only run for requests that match everything else.
        if (!$isMismatch) {
            foreach ($callbackMatchers as $index => $matcher) {
                $results[$index] = $matcher($realRequest);
            }
        }

        ksort($results);

        $result = MatchResult::create($this->name);
        foreach ($results as $matcherResult) {
            $result = $result->withResult($matcherResult);
        }

        return $result;
    }
}
