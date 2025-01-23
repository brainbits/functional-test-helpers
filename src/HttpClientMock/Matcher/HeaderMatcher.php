<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function sprintf;
use function strtolower;

final readonly class HeaderMatcher implements Matcher
{
    private string $header;

    public function __construct(string $header, private mixed $value)
    {
        $this->header = strtolower($header);
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch|Missing
    {
        if (!$realRequest->hasHeader($this->header)) {
            return Missing::missingHeader($this->header, $this->value);
        }

        $expectedValue = $this->value;
        $realValue = $realRequest->getHeader($this->header);

        if ($expectedValue !== $realValue) {
            return Mismatch::mismatchingHeader($this->header, $expectedValue, $realValue);
        }

        return Hit::matchesHeader($this->header, $realValue);
    }

    public function __toString(): string
    {
        return sprintf('request.header["%s"] === "%s"', $this->value, $this->header);
    }
}
