<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatcher;
use PHPUnit\Framework\TestCase;

/** @covers \Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatcher */
final class MockRequestMatcherTest extends TestCase
{
    private MockRequestMatcher $matcher;
    private MockRequestBuilder $requestBuilderA;
    private MockRequestBuilder $requestBuilderB;

    protected function setUp(): void
    {
        $this->matcher = new MockRequestMatcher();

        $this->requestBuilderA = new MockRequestBuilder();
        $this->requestBuilderB = new MockRequestBuilder();
    }

    public function testDetectMatchingRequestParameters(): void
    {
        $this->requestBuilderA->requestParam('one', '1');
        $this->requestBuilderA->requestParam('two', '2');

        $this->requestBuilderB->requestParam('two', '2');
        $this->requestBuilderB->requestParam('one', '1');

        $match = ($this->matcher)($this->requestBuilderA, $this->requestBuilderB);

        self::assertMatchScoreIs(5, $match);
    }

    public function testDetectUriWithString(): void
    {
        $this->requestBuilderA->uri('/host');
        $this->requestBuilderB->uri('/host');

        $match = ($this->matcher)($this->requestBuilderA, $this->requestBuilderB);

        self::assertMatchScoreIs(20, $match);
    }

    public function testUriDoesNotMatchWithString(): void
    {
        $this->requestBuilderA->uri('/host');
        $this->requestBuilderB->uri('/does-not-match');

        $match = ($this->matcher)($this->requestBuilderA, $this->requestBuilderB);

        self::assertMatchScoreIs(0, $match);
    }

    public function testDetectUriWithCallback(): void
    {
        $this->requestBuilderA->uri(static fn ($uri) => $uri === '/host');
        $this->requestBuilderB->uri('/host');

        $match = ($this->matcher)($this->requestBuilderA, $this->requestBuilderB);

        self::assertMatchScoreIs(20, $match);
    }

    public function testUriDoesNotMatchWithCallback(): void
    {
        $this->requestBuilderA->uri(static fn ($uri) => $uri === '/host');
        $this->requestBuilderB->uri('/does-not-match');

        $match = ($this->matcher)($this->requestBuilderA, $this->requestBuilderB);

        self::assertMatchScoreIs(0, $match);
    }

    private static function assertMatchScoreIs(int $expected, MockRequestMatch $match): void
    {
        self::assertSame($expected, $match->getScore());
    }
}
