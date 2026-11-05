<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Missing;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\QueryParamMatcher;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryParamMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
#[CoversClass(Missing::class)]
final class QueryParamMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testMatchQueryParam(): void
    {
        $matcher = new QueryParamMatcher('filter', 'lastname', []);

        $realRequest = $this->createRealRequest(queryParams: ['filter' => 'lastname']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('queryParam', $result);
    }

    public function testMatchQueryParamWithZeroValue(): void
    {
        $matcher = new QueryParamMatcher('filter', '0', []);

        $realRequest = $this->createRealRequest(queryParams: ['filter' => '0']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('queryParam', $result);
    }

    public function testMatchQueryParamWithFirstArrayValue(): void
    {
        $matcher = new QueryParamMatcher('name[]', 'abc', []);

        $realRequest = $this->createRealRequest(queryParams: ['name' => ['abc', 'def']]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('queryParam', $result);
    }

    public function testMatchQueryParamWithSecondArrayValue(): void
    {
        $matcher = new QueryParamMatcher('name[]', 'def', []);

        $realRequest = $this->createRealRequest(queryParams: ['name' => ['abc', 'def']]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('queryParam', $result);
    }

    public function testMatchQueryParamWithArrayValue(): void
    {
        $matcher = new QueryParamMatcher('name', ['abc', 'def'], []);

        $realRequest = $this->createRealRequest(queryParams: ['name' => ['abc', 'def']]);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('queryParam', $result);
    }

    public function testMatchQueryParamWithPlaceholder(): void
    {
        $matcher = new QueryParamMatcher('filter', '%s%s', ['last', 'name']);

        $realRequest = $this->createRealRequest(queryParams: ['filter' => 'lastname']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('queryParam', $result);
    }

    public function testMissesQueryParam(): void
    {
        $matcher = new QueryParamMatcher('filter', 'lastname', []);

        $realRequest = $this->createRealRequest();

        $result = $matcher($realRequest);

        self::assertInstanceOf(Missing::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('queryParam', $result);
    }

    public function testMismatchQueryParam(): void
    {
        $matcher = new QueryParamMatcher('filter', 'lastname', []);

        $realRequest = $this->createRealRequest(queryParams: ['filter' => 'does-not-match']);

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('queryParam', $result);
    }

    public function testToString(): void
    {
        $matcher = new QueryParamMatcher('filter', 'lastname', []);

        self::assertSame('request.queryParams["filter"] === "lastname"', (string) $matcher);
    }

    public function testArrayQueryParamAgainstScalarValueIsAMismatch(): void
    {
        $matcher = new QueryParamMatcher('ids[]', '1', []);

        $result = $matcher($this->createRealRequest(queryParams: ['ids' => '1']));

        self::assertInstanceOf(Mismatch::class, $result);
    }

    public function testValueWithPercentSignAndWithoutPlaceholders(): void
    {
        $matcher = new QueryParamMatcher('q', '50%', []);

        $result = $matcher($this->createRealRequest(queryParams: ['q' => '50%']));

        self::assertInstanceOf(Hit::class, $result);
    }

    public function testValueWithEscapedPercentSign(): void
    {
        $matcher = new QueryParamMatcher('q', '50%%', []);

        $result = $matcher($this->createRealRequest(queryParams: ['q' => '50%']));

        self::assertInstanceOf(Hit::class, $result);
    }
}
