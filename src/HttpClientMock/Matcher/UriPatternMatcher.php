<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function Safe\preg_match;
use function sprintf;

/**
 * Matches the URI against a regular expression, e.g. '#^https://api\.test/users/\d+$#'.
 */
final readonly class UriPatternMatcher implements Matcher
{
    public function __construct(private string $pattern)
    {
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch
    {
        $realUri = $realRequest->getUri();

        if (preg_match($this->pattern, $realUri) !== 1) {
            return Mismatch::mismatchingUri($this->pattern, $realUri);
        }

        return Hit::matchesUri($realUri);
    }

    public function __toString(): string
    {
        return sprintf('request.uri matches "%s"', $this->pattern);
    }
}
