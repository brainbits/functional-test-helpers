<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\ThatMatcher;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ThatMatcher::class)]
#[CoversClass(Hit::class)]
#[CoversClass(Mismatch::class)]
final class ThatMatcherTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testMatchThatWithExplicitReturnTrue(): void
    {
        $matcher = new ThatMatcher(static fn ($realRequest) => $realRequest->getContent() === 'test');

        $realRequest = $this->createRealRequest(content: 'test');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('that', $result);
    }

    public function testMatchThatWithNoReturn(): void
    {
        $matcher = new ThatMatcher(static function ($realRequest): void {
        });

        $realRequest = $this->createRealRequest(content: 'test');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Hit::class, $result);
        self::assertScore(5, $result);
        self::assertMatcher('that', $result);
    }

    public function testMismatchThatWithExplicitReturnFalse(): void
    {
        $matcher = new ThatMatcher(static fn ($realRequest) => $realRequest->getContent() === 'test');

        $realRequest = $this->createRealRequest(content: 'does-not-match');

        $result = $matcher($realRequest);

        self::assertInstanceOf(Mismatch::class, $result);
        self::assertScore(0, $result);
        self::assertMatcher('that', $result);
    }

    public function testToString(): void
    {
        $matcher = new ThatMatcher(static fn ($realRequest) => $realRequest->getContent() === 'test');

        self::assertSame('callback(request)', (string) $matcher);
    }
}
