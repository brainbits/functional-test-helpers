<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoMatchingMockRequest;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestResolver;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockResponseBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MockRequestResolver::class)]
final class MockRequestResolverTest extends TestCase
{
    use RealRequestTrait;

    public function testEmptyCollection(): void
    {
        $this->expectException(NoMatchingMockRequest::class);
        $this->expectExceptionMessage('No mock request builders given for:
GET /query');

        $realRequest = $this->createRealRequest('GET', '/query');

        $collection = new MockRequestBuilderCollection();

        (new MockRequestResolver())($collection, $realRequest);
    }

    public function testNoMatchWithMissingKeys(): void
    {
        $this->expectException(NoMatchingMockRequest::class);
        $this->expectExceptionMessage(<<<'MSG'
            No matching mock request builder found for:
            GET /does-not-match
            
            Mock request builders:
            #1 test-with-missing
              ✘ method "GET" does not match "POST" (0)
              ✘ uri "/does-not-match" does not match "/query" (0)
              ✘ header accept missing (0)
              ✘ queryParam filter missing (0)
              ✘ requestParam firstname missing (0)
              ✘ multipart file missing (0)
              ✘ content "NULL" does not match "this is plain text" (0)
            MSG);

        $requestBuilder1 = (new MockRequestBuilder())
            ->name('test-with-missing')
            ->method('POST')
            ->uri('/query')
            ->header('Accept', 'text/plain')
            ->queryParam('filter', 'lastname')
            ->requestParam('firstname', 'tester')
            ->multipart('file')
            ->content('this is plain text');

        $realRequest = $this->createRealRequest('GET', '/does-not-match');

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);

        (new MockRequestResolver())($collection, $realRequest);
    }

    public function testNoMatchWithMismatchingKeys(): void
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        $this->expectException(NoMatchingMockRequest::class);
        $this->expectExceptionMessage(<<<'MSG'
            No matching mock request builder found for:
            GET /query?filter=firstname
            accept: text/csv
            &firstname=peter
            
            Mock request builders:
            #1 test-with-mismatch
              ✔ method matches "GET" (10)
              ✔ uri matches "/query" (20)
              ✘ header accept "text/csv" does not match "text/plain" (0)
              ✘ queryParam filter "firstname" does not match "lastname" (0)
              ✘ requestParam firstname "peter" does not match "tester" (0)
              ✘ multipart file "{"name":"file","mimetype":null}" does not match "{"name":"file","mimetype":"picture.jpg"}" (0)
              ✘ content "text" does not match "this is plain text" (0)
            MSG);
        // phpcs:enable Generic.Files.LineLength.TooLong

        $requestBuilder1 = (new MockRequestBuilder())
            ->name('test-with-mismatch')
            ->method('GET')
            ->uri('/query')
            ->header('Accept', 'text/plain')
            ->queryParam('filter', 'lastname')
            ->requestParam('firstname', 'tester')
            ->multipart('file', 'picture.jpg')
            ->content('this is plain text');

        $realRequest = $this->createRealRequest(
            'GET',
            '/query',
            headers: ['Accept' => 'text/csv'],
            content: 'text',
            queryParams: ['filter' => 'firstname'],
            requestParams: ['firstname' => 'peter'],
            multiparts: ['file' => ['name' => 'file', 'filename' => 'wrong.jpg']],
        );

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);

        (new MockRequestResolver())($collection, $realRequest);
    }

    public function testNoMatchWithContent(): void
    {
        $this->expectException(NoMatchingMockRequest::class);
        $this->expectExceptionMessage(<<<'MSG'
            No matching mock request builder found for:
            GET /query?filter=lastname
            accept: text/plain
            this is non-matching-text
            
            Mock request builders:
            #1 test-with-content
              ✔ method matches "GET" (10)
              ✔ uri matches "/query" (20)
              ✔ header accept matches "text/plain" (5)
              ✔ queryParam filter matches "lastname" (5)
              ✘ content "this is non-matching-text" does not match "this is plain text" (0)
            MSG);

        $requestBuilder1 = (new MockRequestBuilder())
            ->name('test-with-content')
            ->method('GET')
            ->uri('/query')
            ->header('Accept', 'text/plain')
            ->queryParam('filter', 'lastname')
            ->content('this is plain text');

        $realRequest = $this->createRealRequest(
            'GET',
            '/query',
            headers: ['Accept' => 'text/plain'],
            content: 'this is non-matching-text',
            queryParams: ['filter' => 'lastname'],
        );

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);

        (new MockRequestResolver())($collection, $realRequest);
    }

    public function testNoMatchWithJson(): void
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        $this->expectException(NoMatchingMockRequest::class);
        $this->expectExceptionMessage(<<<'MSG'
            No matching mock request builder found for:
            GET /query?filter=lastname
            accept: text/plain
            {"firstname":"peter","lastname":"peterson","address":{"street":"bobstreet 1","zip":"12345","city":"peterstown"}}
            
            Mock request builders:
            #1 test-with-content
              ✔ method matches "GET" (10)
              ✔ uri matches "/query" (20)
              ✔ header accept matches "text/plain" (5)
              ✔ queryParam filter matches "lastname" (5)
              ✘ json "{"firstname":"peter","lastname":"peterson","address":{"street":"bobstreet 1","zip":"12345","city":"peterstown"}}" does not match "{"firstname":"peter","lastname":"peterson","address":{"street":"peterstreet 1","zip":"12345","city":"peterstown"}}" (0)
                --- Expected
                +++ Actual
                @@ @@
                     "firstname": "peter",
                     "lastname": "peterson",
                     "address": {
                -        "street": "peterstreet 1",
                +        "street": "bobstreet 1",
                         "zip": "12345",
                         "city": "peterstown"
                     }
                 }
            MSG);
        // phpcs:enable Generic.Files.LineLength.TooLong

        $requestBuilder1 = (new MockRequestBuilder())
            ->name('test-with-content')
            ->method('GET')
            ->uri('/query')
            ->header('Accept', 'text/plain')
            ->queryParam('filter', 'lastname')
            ->json([
                'firstname' => 'peter',
                'lastname' => 'peterson',
                'address' => [
                    'street' => 'peterstreet 1',
                    'zip' => '12345',
                    'city' => 'peterstown',
                ],
            ]);

        $realRequest = $this->createRealRequest(
            'GET',
            '/query',
            headers: ['Accept' => 'text/plain'],
            json: [
                'firstname' => 'peter',
                'lastname' => 'peterson',
                'address' => [
                    'street' => 'bobstreet 1',
                    'zip' => '12345',
                    'city' => 'peterstown',
                ],
            ],
            queryParams: ['filter' => 'lastname'],
        );

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);

        (new MockRequestResolver())($collection, $realRequest);
    }

    public function testMatch(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/query');

        $realRequest = $this->createRealRequest('GET', '/query');

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder1, $resultRequestBuilder);
    }

    public function testMultipleMatchesWithResponses(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/query')
            ->willRespond(new MockResponseBuilder());

        $requestBuilder2 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/query')
            ->willRespond(new MockResponseBuilder());

        $realRequest = $this->createRealRequest('GET', '/query');

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);
        $collection->addMockRequestBuilder($requestBuilder2);

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder1, $resultRequestBuilder);
    }

    public function testMatchWithSoftMatching(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/query');

        $realRequest = $this->createRealRequest('GET', '/query', queryParams: ['foo' => '1337']);

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder1, $resultRequestBuilder);
    }

    public function testBestMatch(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/query');

        $requestBuilder2 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/query')
            ->queryParam('foo', '1337');

        $realRequest = $this->createRealRequest('GET', '/query', queryParams: ['foo' => '1337']);

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);
        $collection->addMockRequestBuilder($requestBuilder2);

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder2, $resultRequestBuilder);
    }

    public function testMatchWithProcessedRequest(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/query')
            ->willRespond(new MockResponseBuilder());

        $requestBuilder2 = (new MockRequestBuilder())
            ->method('GET')
            ->uri('/query')
            ->willRespond(new MockResponseBuilder());

        $realRequest = $this->createRealRequest('GET', '/query');

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);
        $collection->addMockRequestBuilder($requestBuilder2);

        $requestBuilder1->nextResponse(); // simulate process request

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder2, $resultRequestBuilder);
    }

    public function testBestMatchWithProcessedRequest(): void
    {
        $requestBuilder1 = (new MockRequestBuilder())
            ->name('one')
            ->method('GET')
            ->uri('/query')
            ->queryParam('foo', '1337')
            ->willRespond(new MockResponseBuilder());

        $requestBuilder2 = (new MockRequestBuilder())
            ->name('two')
            ->method('GET')
            ->uri('/query')
            ->willRespond(new MockResponseBuilder());

        $realRequest = $this->createRealRequest('GET', '/query', queryParams: ['foo' => '1337']);

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder($requestBuilder1);
        $collection->addMockRequestBuilder($requestBuilder2);

        $requestBuilder1->nextResponse(); // simulate process request

        $resultRequestBuilder = (new MockRequestResolver())($collection, $realRequest);

        self::assertSame($requestBuilder2, $resultRequestBuilder);
    }
}
