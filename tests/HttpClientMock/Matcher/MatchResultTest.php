<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MatchResult;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Mismatch;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Missing;
use Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\RealRequestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MatchResult::class)]
final class MatchResultTest extends TestCase
{
    use MatcherTrait;
    use RealRequestTrait;

    public function testNameCanBeNull(): void
    {
        $matchResult1 = MatchResult::create(null);
        $matchResult2 = MatchResult::create('test');

        $this->assertNull($matchResult1->getName());
        $this->assertSame('test', $matchResult2->getName());
    }

    public function testResultsCanBeEmpty(): void
    {
        $matchResult = MatchResult::create('test');

        $this->assertSame([], $matchResult->getResults());
    }

    public function testResultsCanBeSet(): void
    {
        $hit = Hit::matchesMethod('GET');
        $mismatch = Mismatch::mismatchingUri('/query', '/does-not-match');
        $missing = Missing::missingHeader('Content-Type', 'application/json');

        $matchResult = MatchResult::create('test')
            ->withResult($hit)
            ->withResult($mismatch)
            ->withResult($missing);

        $this->assertSame([$hit, $mismatch, $missing], $matchResult->getResults());
    }

    public function testEmptyHasZeroScore(): void
    {
        $matchResult = MatchResult::create('test');

        $this->assertSame(0, $matchResult->getScore());
    }

    public function testEmptyIsNoMismatch(): void
    {
        $matchResult = MatchResult::create('test');

        $this->assertFalse($matchResult->isMismatch());
    }

    public function testOnlyHitsHaveScore(): void
    {
        $matchResult = MatchResult::create('test')
            ->withResult(Hit::matchesMethod('GET'))
            ->withResult(Hit::matchesUri('/query'));

        $this->assertSame(30, $matchResult->getScore());
    }

    public function testOnlyHitsIsNoMismatch(): void
    {
        $matchResult = MatchResult::create('test')
            ->withResult(Hit::matchesMethod('GET'))
            ->withResult(Hit::matchesUri('/query'));

        $this->assertFalse($matchResult->isMismatch());
    }

    public function testGetMixedResultsHaveZeroScore(): void
    {
        $matchResult = MatchResult::create('test')
            ->withResult(Hit::matchesMethod('GET'))
            ->withResult(Mismatch::mismatchingUri('/query', '/does-not-match'))
            ->withResult(Missing::missingHeader('Content-Type', 'application/json'));

        $this->assertSame(0, $matchResult->getScore());
    }

    public function testGetMixedResultsIsMismatch(): void
    {
        $matchResult = MatchResult::create('test')
            ->withResult(Hit::matchesMethod('GET'))
            ->withResult(Mismatch::mismatchingUri('/query', '/does-not-match'))
            ->withResult(Missing::missingHeader('Content-Type', 'application/json'));

        $this->assertTrue($matchResult->isMismatch());
    }
}
