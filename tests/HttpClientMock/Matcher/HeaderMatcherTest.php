<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\HeaderMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Missing;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HeaderMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
#[CoversClass(Missing::class)]
final class HeaderMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testMatchHeader(): void
    {
        $matcher = new HeaderMatcher('Accept', 'text/plain');

        $realRequest = $this->createRealRequest(headers: ['Accept' => 'text/plain']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('header', $result);
    }

    public function testMissesHeader(): void
    {
        $matcher = new HeaderMatcher('Accept', 'text/plain');

        $realRequest = $this->createRealRequest(headers: ['Content-Type' => 'text/plain']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Missing::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('header', $result);
    }

    public function testMismatchesHeader(): void
    {
        $matcher = new HeaderMatcher('Accept', 'text/plain');

        $realRequest = $this->createRealRequest(headers: ['Accept' => 'text/does-not-match']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('header', $result);
    }

    public function testToStringWithCallback(): void
    {
        $matcher = new HeaderMatcher('Accept', 'text/plain');

        self::assertSame('request.header["text/plain"] === "accept"', (string) $matcher);
    }
}
