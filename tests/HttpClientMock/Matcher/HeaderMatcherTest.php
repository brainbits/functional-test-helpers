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

    public function testStringNamingAFunctionIsComparedExactly(): void
    {
        $matcher = new HeaderMatcher('X-Format', 'date');

        self::assertInstanceOf(Mismatch::class, $matcher($this->createRealRequest(headers: ['x-format' => 'iso'])));
        self::assertInstanceOf(Hit::class, $matcher($this->createRealRequest(headers: ['x-format' => 'date'])));
    }

    public function testMatchHeaderWithClosure(): void
    {
        $matcher = new HeaderMatcher('X-Count', static fn (string $value): bool => $value > 1);

        self::assertInstanceOf(Hit::class, $matcher($this->createRealRequest(headers: ['x-count' => '2'])));
        self::assertInstanceOf(Mismatch::class, $matcher($this->createRealRequest(headers: ['x-count' => '1'])));
    }

    public function testToStringWithCallback(): void
    {
        $matcher = new HeaderMatcher('Accept', 'text/plain');

        self::assertSame('request.header["accept"] === "text/plain"', (string) $matcher);
    }
}
