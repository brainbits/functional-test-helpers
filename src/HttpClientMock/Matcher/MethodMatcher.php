<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function is_callable;
use function sprintf;

final readonly class MethodMatcher implements Matcher
{
    /** @var string|callable */
    public mixed $method;

    public function __construct(string|callable $method)
    {
        $this->method = $method;
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch|Missing
    {
        $expectedMethod = $this->method;
        $realMethod = $realRequest->getMethod();

        if (is_callable($expectedMethod)) {
            if ($expectedMethod($realMethod) === false) {
                return Mismatch::mismatchingMethod('<callback>', $realMethod);
            }
        } elseif ($expectedMethod !== $realMethod) {
            return Mismatch::mismatchingMethod($expectedMethod, $realMethod);
        }

        return Hit::matchesMethod($realMethod);
    }

    public function __toString(): string
    {
        return is_callable($this->method)
            ? 'callback(request.method) !== false'
            : sprintf('request.method === "%s"', $this->method);
    }
}
