<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

interface Matcher
{
    public function __invoke(RealRequest $realRequest): Hit|Mismatch|Missing;

    public function __toString(): string;
}
