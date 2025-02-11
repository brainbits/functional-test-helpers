<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\CatchAllMatcher;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CatchAllMatcher::class)]
final class CatchAllMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testMatchContent(): void
    {
        $matcher = new CatchAllMatcher();

        $realRequest = $this->createRealRequest(content: 'this is text');

        $result = $matcher($realRequest);

        self::assertScore(1, $result);
        self::assertMatcher('catchAll', $result);
    }

    public function testToString(): void
    {
        $matcher = new CatchAllMatcher();

        self::assertSame('*', (string) $matcher);
    }
}
