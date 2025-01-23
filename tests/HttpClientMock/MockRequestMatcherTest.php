<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

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
}
