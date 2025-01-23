<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MethodMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MethodMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
final class MethodMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testMatchMethod(): void
    {
        $matcher = new MethodMatcher('GET');

        $realRequest = $this->createRealRequest(method: 'GET');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(10, $result);
        self::assertMatcher('method', $result);
    }

    public function testMatchMethodWithCallback(): void
    {
        $matcher = new MethodMatcher(static fn ($method) => $method === 'GET');

        $realRequest = $this->createRealRequest(method: 'GET');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(10, $result);
        self::assertMatcher('method', $result);
    }

    public function testMismatchMethod(): void
    {
        $matcher = new MethodMatcher('GET');

        $realRequest = $this->createRealRequest(method: 'POST');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('method', $result);
    }

    public function testMismatchMethodWithCallback(): void
    {
        $matcher = new MethodMatcher(static fn ($method) => $method === 'GET');

        $realRequest = $this->createRealRequest(method: 'POST');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('method', $result);
    }

    public function testToString(): void
    {
        $matcher = new MethodMatcher('GET');

        self::assertSame('request.method === "GET"', (string) $matcher);
    }

    public function testToStringWithCallback(): void
    {
        $matcher = new MethodMatcher(static fn ($method) => $method === 'GET');

        self::assertSame('callback(request.method) !== false', (string) $matcher);
    }
}
