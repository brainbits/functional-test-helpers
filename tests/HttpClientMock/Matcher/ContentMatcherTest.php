<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\ContentMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
final class ContentMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testMatchContent(): void
    {
        $matcher = new ContentMatcher('this is text');

        $realRequest = $this->createRealRequest(content: 'this is text');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('content', $result);
    }

    public function testMatchContentWithCallback(): void
    {
        $matcher = new ContentMatcher(static fn ($content) => $content === 'this is text');

        $realRequest = $this->createRealRequest(content: 'this is text');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('content', $result);
    }

    public function testMismatchContent(): void
    {
        $matcher = new ContentMatcher('this is text');

        $realRequest = $this->createRealRequest(content: 'does-not-match');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('content', $result);
    }

    public function testMismatchContentWithCallback(): void
    {
        $matcher = new ContentMatcher(static fn ($content) => $content === 'this is text');

        $realRequest = $this->createRealRequest(content: 'does-not-match');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('content', $result);
    }

    public function testToString(): void
    {
        $matcher = new ContentMatcher('this is text');

        self::assertSame('request.content === "this is text"', (string) $matcher);
    }

    public function testToStringWithCallback(): void
    {
        $matcher = new ContentMatcher(static fn ($content) => $content === 'this is text');

        self::assertSame('callback(request.content) !== false', (string) $matcher);
    }
}
