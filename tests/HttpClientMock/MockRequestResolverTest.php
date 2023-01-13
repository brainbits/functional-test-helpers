<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoMatchingMockRequest;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestResolver;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockResponseBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\SymfonyMockResponseFactory;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestResolver
 */
final class MockRequestResolverTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $this->expectException(NoMatchingMockRequest::class);

        $realRequest = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar');

        $collection = new MockRequestBuilderCollection(new SymfonyMockResponseFactory());

        (new MockRequestResolver())($collection, $realRequest);
    }

    public function testNoMatch(): void
    {
        $this->expectException(NoMatchingMockRequest::class);

        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/foo');

        $realRequest = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar');

        $collection = new MockRequestBuilderCollection(new SymfonyMockResponseFactory());
        $collection->addMockRequestBuilder($requestBuilder1);

        (new MockRequestResolver())($collection, $realRequest);
    }

    public function testMatch(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar');

        $realRequest = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar');

        $collection = new MockRequestBuilderCollection(new SymfonyMockResponseFactory());
        $collection->addMockRequestBuilder($requestBuilder1);

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder1, $resultRequestBuilder);
    }

    public function testMultipleMatchWithResponses(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->willRespond(new MockResponseBuilder());

        $requestBuilder2 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->willRespond(new MockResponseBuilder());

        $realRequest = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar');

        $collection = new MockRequestBuilderCollection(new SymfonyMockResponseFactory());
        $collection->addMockRequestBuilder($requestBuilder1);
        $collection->addMockRequestBuilder($requestBuilder2);

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder1, $resultRequestBuilder);
    }

    public function testMatchWithSoftMatching(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar');

        $realRequest = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->queryParam('foo', '1337');

        $collection = new MockRequestBuilderCollection(new SymfonyMockResponseFactory());
        $collection->addMockRequestBuilder($requestBuilder1);

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder1, $resultRequestBuilder);
    }

    public function testBestMatch(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar');

        $requestBuilder2 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->queryParam('foo', '1337');

        $realRequest = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->queryParam('foo', '1337');

        $collection = new MockRequestBuilderCollection(new SymfonyMockResponseFactory());
        $collection->addMockRequestBuilder($requestBuilder1);
        $collection->addMockRequestBuilder($requestBuilder2);

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder2, $resultRequestBuilder);
    }

    public function testMatchWithProcessedRequest(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->willRespond(new MockResponseBuilder());

        $requestBuilder2 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->willRespond(new MockResponseBuilder());

        $realRequest = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar');

        $collection = new MockRequestBuilderCollection(new SymfonyMockResponseFactory());
        $collection->addMockRequestBuilder($requestBuilder1);
        $collection->addMockRequestBuilder($requestBuilder2);

        $requestBuilder1->nextResponse(); // simulate process request

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder2, $resultRequestBuilder);
    }

    public function testBestMatchWithProcessedRequest(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->queryParam('foo', '1337')
            ->willRespond(new MockResponseBuilder());

        $requestBuilder2 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->willRespond(new MockResponseBuilder());

        $realRequest = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/bar')
            ->queryParam('foo', '1337');

        $collection = new MockRequestBuilderCollection(new SymfonyMockResponseFactory());
        $collection->addMockRequestBuilder($requestBuilder1);
        $collection->addMockRequestBuilder($requestBuilder2);

        $requestBuilder1->nextResponse(); // simulate process request

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder1, $resultRequestBuilder);
    }
}
