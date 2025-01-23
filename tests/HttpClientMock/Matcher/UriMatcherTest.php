<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\UriContainsQueryParameters;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriParams;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UriMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
final class UriMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testUriShouldNotContainQueryParameters(): void
    {
        $this->expectException(UriContainsQueryParameters::class);
        $this->expectExceptionMessage(
            'Given uri /query?foo=bar conts query parameters, use queryParam() calls instead.',
        );

        new UriMatcher('/query?foo=bar', new UriParams());
    }

    public function testMatchUri(): void
    {
        $matcher = new UriMatcher('/query', new UriParams());

        $realRequest = $this->createRealRequest(uri: '/query');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertMatcher('uri', $result);
        self::assertScore(20, $result);
    }

    public function testUriDoesNotMatch(): void
    {
        $matcher = new UriMatcher('/query', new UriParams());

        $realRequest = $this->createRealRequest(uri: '/does-not-match');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertMatcher('uri', $result);
        self::assertScore(0, $result);
    }

    public function testMatchUriWithUriParams(): void
    {
        $matcher = new UriMatcher('/query/{tpl}', new UriParams(['tpl' => 'test']));

        $realRequest = $this->createRealRequest(uri: '/query/test');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertMatcher('uri', $result);
        self::assertScore(20, $result);
    }

    public function testMatchUriWithCallback(): void
    {
        $matcher = new UriMatcher(static fn ($uri) => $uri === '/query', new UriParams());

        $realRequest = $this->createRealRequest(uri: '/query');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertMatcher('uri', $result);
        self::assertScore(20, $result);
    }

    public function testMatchUriWithCallbackAndUriParams(): void
    {
        $matcher = new UriMatcher(
            static fn ($uri, $params) => $uri === '/query/' . $params['id'],
            new UriParams(['id' => 'test']),
        );

        $realRequest = $this->createRealRequest(uri: '/query/test');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertMatcher('uri', $result);
        self::assertScore(20, $result);
    }

    public function testUriDoesNotMatchWithUriParams(): void
    {
        $matcher = new UriMatcher('/query/{tpl}', new UriParams(['tpl' => 'test']));

        $realRequest = $this->createRealRequest(uri: '/query/does-not-match');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertMatcher('uri', $result);
        self::assertScore(0, $result);
    }

    public function testUriDoesNotMatchWithCallback(): void
    {
        $matcher = new UriMatcher(static fn ($uri) => $uri === '/query', new UriParams());

        $realRequest = $this->createRealRequest(uri: '/does-not-match');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertMatcher('uri', $result);
        self::assertScore(0, $result);
    }

    public function testUriDoesNotMatchWithCallbackAndUriParams(): void
    {
        $matcher = new UriMatcher(
            static fn ($uri, $params) => $uri === '/query/' . $params['id'],
            new UriParams(['id' => 'test']),
        );

        $realRequest = $this->createRealRequest(uri: '/query/does-not-match');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertMatcher('uri', $result);
        self::assertScore(0, $result);
    }

    public function testToString(): void
    {
        $matcher = new UriMatcher('/query', new UriParams());

        self::assertSame('request.uri === "/query"', (string) $matcher);
    }

    public function testToStringWithCallback(): void
    {
        $matcher = new UriMatcher(static fn ($uri) => $uri === '/query', new UriParams());

        self::assertSame('callback(request.uri, {}) !== false', (string) $matcher);
    }

    public function testToStringWithCallbackAndParams(): void
    {
        $matcher = new UriMatcher(static fn ($uri) => $uri === '/query/{id}', new UriParams(['{id}' => 'abc']));

        self::assertSame('callback(request.uri, {"{id}":"abc"}) !== false', (string) $matcher);
    }
}
