<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Matcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MatchResult;

final readonly class MockRequestMatcher
{
    /** @param Matcher[] $matchers */
    public function __construct(private string|null $name, private array $matchers)
    {
    }

    public function __invoke(RealRequest $realRequest): MatchResult
    {
        $result = MatchResult::create($this->name);

        foreach ($this->matchers as $matcher) {
            $result = $result->withResult($matcher($realRequest));
        }

        return $result;
    }
}
