<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Missing;

trait MatcherTrait
{
    private static function assertScore(int $expected, Hit|Mismatch|Missing $result): void
    {
        self::assertSame($expected, $result->score);
    }

    private static function assertMatcher(string $matcher, Hit|Mismatch|Missing $result): void
    {
        self::assertSame($matcher, $result->matcher);
    }
}
