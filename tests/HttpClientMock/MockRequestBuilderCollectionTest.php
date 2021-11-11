<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection;
use Brainbits\FunctionalTestHelpers\HttpClientMock\SymfonyMockResponseFactory;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder
 * @covers \Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection
 * @covers \Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatch
 * @covers \Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatcher
 */
final class MockRequestBuilderCollectionTest extends TestCase
{
    use ProphecyTrait;

    private MockRequestBuilderCollection $collection;
    /** @var MockRequestBuilder[] */
    private array $builders = [];

    public function setUp(): void
    {
        $this->builders = [
            'fallback' => (new MockRequestBuilder()),

            'get' => (new MockRequestBuilder())
                ->method('GET'),

            'post' => (new MockRequestBuilder())
                ->method('POST'),

            'foo' => (new MockRequestBuilder())
                ->uri('/foo'),

            'getBar' => (new MockRequestBuilder())
                ->method('GET')
                ->uri('/bar'),

            'getBarWithOneParam' => (new MockRequestBuilder())
                ->method('GET')
                ->uri('/bar?one=1'),

            'getBarWithTwoParams' => (new MockRequestBuilder())
                ->method('GET')
                ->uri('/bar')
                ->queryParam('one', '1')
                ->queryParam('two', '2'),

            'postBarJson' => (new MockRequestBuilder())
                ->method('POST')
                ->uri('/bar')
                ->json(['json' => 'data']),

            'postBarWithOneParam' => (new MockRequestBuilder())
                ->method('POST')
                ->uri('/bar')
                ->requestParam('one', '1'),

            'postBarWithTwoParams' => (new MockRequestBuilder())
                ->method('POST')
                ->uri('/bar')
                ->requestParam('one', '1')
                ->requestParam('two', '2'),

            'postBarWithContent' => (new MockRequestBuilder())
                ->method('POST')
                ->uri('/bar')
                ->content('content'),
        ];

        $this->collection = new MockRequestBuilderCollection(new SymfonyMockResponseFactory());
        foreach ($this->builders as $builder) {
            $this->collection->addMockRequestBuilder($builder);
        }
    }

    /**
     * @param mixed[] $options
     *
     * @dataProvider requests
     */
    public function testRequestMatching(string $method, string $uri, array $options, string $index): void
    {
        ($this->collection)($method, $uri, $options);

        $expectedMockRequestBuilder = $this->builders[$index];

        self::assertFalse($expectedMockRequestBuilder->getCallStack()->isEmpty());
    }

    /**
     * @return mixed[]
     */
    public function requests(): array
    {
        return [
            ['DELETE', '/baz', [], 'fallback'],
            ['GET', '/baz', [], 'get'],
            ['POST', '/baz', [], 'post'],
            ['GET', '/foo', [], 'foo'],
            ['POST', '/foo', [], 'foo'],
            ['DELETE', '/foo', [], 'foo'],
            ['GET', '/bar', [], 'getBar'],
            ['GET', '/bar?one=1', [], 'getBarWithOneParam'],
            ['GET', '/bar?one=1&two=2', [], 'getBarWithTwoParams'],
            ['GET', '/bar', [], 'getBar'],
            ['POST', '/bar', [], 'post'],
            ['POST', '/bar', ['json' => ['json' => 'data']], 'postBarJson'],
            'postBarWithOneParam' => [
                'POST',
                '/bar',
                ['body' => 'one=1', 'headers' => ['Content-Type: application/x-www-form-urlencoded']],
                'postBarWithOneParam',
            ],
            'postBarWithTwoParams' => [
                'POST',
                '/bar',
                ['body' => 'one=1&two=2', 'headers' => ['Content-Type: application/x-www-form-urlencoded']],
                'postBarWithTwoParams',
            ],
            'postBarWithContent' => [
                'POST',
                '/bar',
                ['body' => 'content', 'headers' => ['Content-Type: application/x-www-form-urlencoded']],
                'postBarWithContent',
            ],
        ];
    }
}
