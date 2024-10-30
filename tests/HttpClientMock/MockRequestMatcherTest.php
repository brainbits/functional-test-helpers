<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MockRequestMatcher::class)]
final class MockRequestMatcherTest extends TestCase
{
    private MockRequestMatcher $matcher;
    private MockRequestBuilder $expectation;
    private MockRequestBuilder $realRequest;

    protected function setUp(): void
    {
        $this->matcher = new MockRequestMatcher();

        $this->expectation = new MockRequestBuilder();
        $this->realRequest = new MockRequestBuilder();
    }

    public function testDetectMatchingRequestParameters(): void
    {
        $this->expectation->requestParam('one', '1');
        $this->expectation->requestParam('two', '2');

        $this->realRequest->requestParam('two', '2');
        $this->realRequest->requestParam('one', '1');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(5, $match);
    }

    public function testDetectUriWithString(): void
    {
        $this->expectation->uri('/host');
        $this->realRequest->uri('/host');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(20, $match);
    }

    public function testUriDoesNotMatchWithString(): void
    {
        $this->expectation->uri('/host');
        $this->realRequest->uri('/does-not-match');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(0, $match);
    }

    public function testDetectUriWithCallback(): void
    {
        $this->expectation->uri(static fn ($uri) => $uri === '/host');
        $this->realRequest->uri('/host');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(20, $match);
    }

    public function testUriDoesNotMatchWithCallback(): void
    {
        $this->expectation->uri(static fn ($uri) => $uri === '/host');
        $this->realRequest->uri('/does-not-match');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(0, $match);
    }

    public function testMatchesWithSameMultiparts(): void
    {
        $this->expectation->multipart('file', 'application/pdf', 'file.pdf', 'pdf');
        $this->realRequest->multipart('file', 'application/pdf', 'file.pdf', 'pdf');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(5, $match);
    }

    public function testDoesNotMatchWithDifferentMultiparts(): void
    {
        $this->expectation->multipart('file', 'application/pdf', 'file.pdf', 'pdf');
        $this->realRequest->multipart('wrong_file', 'application/pdf', 'file.pdf', 'pdf');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(0, $match);
        // phpcs:ignore Generic.Files.LineLength.TooLong
        self::assertReason('Mismatching multiparts, expected {"file":{"mimetype":"application\/pdf","filename":"file.pdf","content":"pdf"}}, got {"wrong_file":{"mimetype":"application\/pdf","filename":"file.pdf","content":"pdf"}}', $match);
    }

    public function testMatchesWithIncompleteMultiparts(): void
    {
        $this->expectation->multipart('file');
        $this->realRequest->multipart('file', 'application/pdf', 'file.pdf', 'pdf');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(5, $match);
    }

    public function testMatchesWithoutMimetype(): void
    {
        $this->expectation->multipart('file', null, 'file.pdf', 'pdf');
        $this->realRequest->multipart('file', 'application/pdf', 'file.pdf', 'pdf');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(5, $match);
    }

    public function testMatchesWithoutFilename(): void
    {
        $this->expectation->multipart('file', null, null, 'pdf');
        $this->realRequest->multipart('file', 'application/pdf', 'file.pdf', 'pdf');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(5, $match);
    }

    public function testDoesNotMatchWithIncompleteMultiparts(): void
    {
        $this->expectation->multipart('file', null, null, 'pdf');
        $this->realRequest->multipart('file', 'application/pdf', 'file.pdf', 'foo');

        $match = ($this->matcher)($this->expectation, $this->realRequest);

        self::assertMatchScoreIs(0, $match);
        // phpcs:ignore Generic.Files.LineLength.TooLong
        self::assertReason('Mismatching multiparts, expected {"file":{"content":"pdf"}}, got {"file":{"content":"foo"}}', $match);
    }

    private static function assertMatchScoreIs(int $expected, MockRequestMatch $match): void
    {
        self::assertSame($expected, $match->getScore());
    }

    private static function assertReason(string $reason, MockRequestMatch $match): void
    {
        self::assertSame($reason, $match->getReason());
    }
}
