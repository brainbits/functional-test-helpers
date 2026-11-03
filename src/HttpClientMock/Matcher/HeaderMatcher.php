<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;
use Closure;

use function sprintf;
use function strtolower;

final readonly class HeaderMatcher implements CallbackMatcher
{
    private string $header;

    public function __construct(string $header, private string|Closure $value)
    {
        $this->header = strtolower($header);
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch|Missing
    {
        if (!$realRequest->hasHeader($this->header)) {
            return Missing::missingHeader($this->header, $this->value instanceof Closure ? '<callback>' : $this->value);
        }

        $expectedValue = $this->value;
        $realValue = (string) $realRequest->getHeader($this->header);

        if ($expectedValue instanceof Closure) {
            if ($expectedValue($realValue) === false) {
                return Mismatch::mismatchingHeader($this->header, '<callback>', $realValue);
            }
        } elseif ($expectedValue !== $realValue) {
            return Mismatch::mismatchingHeader($this->header, $expectedValue, $realValue);
        }

        return Hit::matchesHeader($this->header, $realValue);
    }

    public function isCallback(): bool
    {
        return $this->value instanceof Closure;
    }

    public function __toString(): string
    {
        return $this->value instanceof Closure
            ? sprintf('callback(request.header["%s"]) !== false', $this->header)
            : sprintf('request.header["%s"] === "%s"', $this->header, $this->value);
    }
}
