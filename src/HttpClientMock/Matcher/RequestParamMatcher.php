<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function sprintf;

final readonly class RequestParamMatcher implements Matcher
{
    public function __construct(private string $key, private mixed $value)
    {
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch|Missing
    {
        if (!$realRequest->hasRequestParam($this->key)) {
            return Missing::missingRequestParam($this->key, $this->value);
        }

        $expectedValue = $this->value;
        $realValue = $realRequest->getRequestParam($this->key);

        if ($expectedValue !== $realValue) {
            return Mismatch::mismatchingRequestParam($this->key, $expectedValue, $realValue);
        }

        return Hit::matchesRequestParam($this->key, $realValue);
    }

    public function __toString(): string
    {
        return sprintf('request.requestParams["%s"] === "%s"', $this->key, $this->value);
    }
}
