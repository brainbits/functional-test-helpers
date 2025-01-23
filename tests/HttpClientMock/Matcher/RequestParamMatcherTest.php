<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Missing;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\RequestParamMatcher;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestParamMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
#[CoversClass(Missing::class)]
final class RequestParamMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testMatchRequestParam(): void
    {
        $matcher = new RequestParamMatcher('firstname', 'tester');

        $realRequest = $this->createRealRequest(requestParams: ['firstname' => 'tester']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('requestParam', $result);
    }

    public function testMissesRequestParam(): void
    {
        $matcher = new RequestParamMatcher('firstname', 'tester');

        $realRequest = $this->createRealRequest();

        $result = $matcher($realRequest);

        self::assertInstanceOf(Missing::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('requestParam', $result);
    }

    public function testMismatchRequestParam(): void
    {
        $matcher = new RequestParamMatcher('firstname', 'tester');

        $realRequest = $this->createRealRequest(requestParams: ['firstname' => 'does-not-match']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('requestParam', $result);
    }

    public function testToString(): void
    {
        $matcher = new RequestParamMatcher('firstname', 'tester');

        self::assertSame('request.requestParams["firstname"] === "tester"', (string) $matcher);
    }
}
