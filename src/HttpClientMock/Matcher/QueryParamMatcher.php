<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function sprintf;

final readonly class QueryParamMatcher implements Matcher
{
    private string $value;

    /** @param array<string> $placeholders */
    public function __construct(private string $key, string $value, array $placeholders)
    {
        $this->value = sprintf($value, ...$placeholders);
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch|Missing
    {
        if (!$realRequest->hasQueryParam($this->key)) {
            return Missing::missingQueryParam($this->key, $this->value);
        }

        $expectedValue = $this->value;
        $realValue = $realRequest->getQueryParam($this->key);

        if ($expectedValue !== $realValue) {
            return Mismatch::mismatchingQueryParam($this->key, $expectedValue, $realValue);
        }

        return Hit::matchesQueryParam($this->key, $realValue);
    }

    public function __toString(): string
    {
        return sprintf('request.queryParams["%s"] === "%s"', $this->key, $this->value);
    }
}
