<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

final readonly class CatchAllMatcher implements Matcher
{
    public function __invoke(RealRequest $realRequest): Hit
    {
        return Hit::catchAll();
    }

    public function __toString(): string
    {
        return '*';
    }
}
