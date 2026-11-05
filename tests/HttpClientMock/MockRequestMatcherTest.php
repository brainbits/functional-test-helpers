<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MockRequestMatcher::class)]
final class MockRequestMatcherTest extends TestCase
{
    use RealRequestTrait;

    public function testIt(): void
    {
        $matcher = new MockRequestMatcher('test', []);
        $result = ($matcher)($this->createRealRequest());

        $this->assertSame('test', $result->getName());
    }

    public function testCallbackMatchersAreSkippedIfOtherMatchersMismatch(): void
    {
        $called = false;

        $matcher = (new MockRequestBuilder())
            ->that(static function () use (&$called): bool {
                $called = true;

                return true;
            })
            ->method('POST')
            ->uri('/a')
            ->getMatcher();

        $result = ($matcher)($this->createRealRequest('GET', '/b'));

        $this->assertFalse($called, 'Callback was invoked for a non-matching request');
        $this->assertTrue($result->isMismatch());
        $this->assertCount(2, $result->getResults());
    }

    public function testCallbackMatchersRunIfOtherMatchersMatch(): void
    {
        $called = false;

        $matcher = (new MockRequestBuilder())
            ->that(static function () use (&$called): bool {
                $called = true;

                return true;
            })
            ->method('GET')
            ->uri('/b')
            ->getMatcher();

        $result = ($matcher)($this->createRealRequest('GET', '/b'));

        $this->assertTrue($called);
        $this->assertFalse($result->isMismatch());
        $this->assertCount(3, $result->getResults());
    }

    public function testResultsKeepTheOrderOfTheMatchers(): void
    {
        $matcher = (new MockRequestBuilder())
            ->that(static fn () => true)
            ->method('GET')
            ->getMatcher();

        $results = ($matcher)($this->createRealRequest('GET', '/b'))->getResults();

        $this->assertSame('that', $results[0]->matcher);
        $this->assertSame('method', $results[1]->matcher);
    }
}
