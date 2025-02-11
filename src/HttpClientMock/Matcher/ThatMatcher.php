<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

final readonly class ThatMatcher implements Matcher
{
    private mixed $that;

    public function __construct(callable $that)
    {
        $this->that = $that;
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch
    {
        if (($this->that)($realRequest) === false) {
            return Mismatch::mismatchingThat('returned false');
        }

        return Hit::matchesThat();
    }

    public function __toString(): string
    {
        return 'callback(request)';
    }
}
