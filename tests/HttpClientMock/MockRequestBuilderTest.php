<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\AddMockResponseFailed;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\InvalidMockRequest;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoResponseMock;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\HeaderMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\JsonMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MethodMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MultipartMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\QueryParamMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\ThatMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriParams;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\XmlMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockResponseBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;

use const PHP_EOL;

#[CoversClass(MockRequestBuilder::class)]
final class MockRequestBuilderTest extends TestCase
{
    use RealRequestTrait;

    public function testWithoutAnythingSpecifiedARequestIsEmpty(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();

        self::assertTrue($mockRequestBuilder->isEmpty());
    }

    public function testWithMatcherIsNotEmpty(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->method('GET');

        self::assertFalse($mockRequestBuilder->isEmpty());
    }

    public function testMethod(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->method('GET');

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(new MockRequestMatcher(null, [new MethodMatcher('GET')]), $matcher);
    }

    public function testUri(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->uri('/query');

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            new MockRequestMatcher(null, [new UriMatcher('/query', new UriParams())]),
            $matcher,
        );
    }

    public function testUriWithUriParam(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->uri('/query/{tpl}');
        $mockRequestBuilder->uriParam('tpl', 'value');

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            new MockRequestMatcher(null, [new UriMatcher('/query/{tpl}', new UriParams(['tpl' => 'value']))]),
            $matcher,
        );
    }

    public function testQueryParam(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->queryParam('filter', 'firstname');

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            new MockRequestMatcher(null, [new QueryParamMatcher('filter', 'firstname', [])]),
            $matcher,
        );
    }

    public function testMultipleQueryParams(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->queryParam('filter', 'firstname');
        $mockRequestBuilder->queryParam('orderBy', 'lastname');

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            new MockRequestMatcher(
                null,
                [
                    new QueryParamMatcher('filter', 'firstname', []),
                    new QueryParamMatcher('orderBy', 'lastname', []),
                ],
            ),
            $matcher,
        );
    }

    public function testXml(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->xml('<?xml version="1.0"?><root><first>abc</first></root>');

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            new MockRequestMatcher(null, [new XmlMatcher('<?xml version="1.0"?><root><first>abc</first></root>')]),
            $matcher,
        );
    }

    public function testXmlWithInvalidXmlThrowsException(): void
    {
        $this->expectException(InvalidMockRequest::class);
        $this->expectExceptionMessage('No valid xml: foo');

        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->xml('foo');
    }

    public function testJson(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->json(['firstname' => 'peter']);

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            new MockRequestMatcher(null, [new JsonMatcher(['firstname' => 'peter'])]),
            $matcher,
        );
    }

    public function testWithBasicAuthentication(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->basicAuthentication('username', 'password');

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            new MockRequestMatcher(null, [new HeaderMatcher('Authorization', 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=')]),
            $matcher,
        );
    }

    public function testWithMultipart(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->multipart('key', 'mimetype', 'filename', 'content');

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            new MockRequestMatcher(null, [new MultipartMatcher('key', 'mimetype', 'filename', 'content')]),
            $matcher,
        );
    }

    public function testWithMultipartFromFile(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->multipartFromFile('key', new File(__DIR__ . '/../files/test.txt'));

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            new MockRequestMatcher(null, [new MultipartMatcher('key', 'text/plain', 'test.txt', 'this is a txt file' . PHP_EOL)]),
            $matcher,
        );
    }

    public function testThat(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->that(static fn ($request) => true);

        $matcher = $mockRequestBuilder->getMatcher();

        self::assertEquals(
            // phpcs:ignore Generic.Files.LineLength.TooLong
            new MockRequestMatcher(null, [new ThatMatcher(static fn ($request) => true)]),
            $matcher,
        );
    }

    public function testAssertMethod(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->assertMethod(function (string $method): void {
            $this->assertSame('POST', $method);
        });

        $mockRequestBuilder->assert($this->createRealRequest(method: 'POST'));
    }

    public function testAssertMethodFails(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->assertUri(function (string $method): void {
            $this->assertSame('does-not-match', $method);
        });

        try {
            $mockRequestBuilder->assert($this->createRealRequest(method: 'POST'));
        } catch (AssertionFailedError) {
            return;
        }

        $this->fail('Expected assertion error was not thrown');
    }

    public function testAssertUri(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->assertUri(function (string $uri): void {
            $this->assertSame('/query', $uri);
        });

        $mockRequestBuilder->assert($this->createRealRequest(uri: '/query'));
    }

    public function testAssertUriFails(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->assertUri(function (string $content): void {
            $this->assertSame('does-not-match', $content);
        });

        try {
            $mockRequestBuilder->assert($this->createRealRequest(uri: '/query'));
        } catch (AssertionFailedError) {
            return;
        }

        $this->fail('Expected assertion error was not thrown');
    }

    public function testAssertContent(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->assertContent(function (string $content): void {
            $this->assertSame('this is content', $content);
        });

        $mockRequestBuilder->assert($this->createRealRequest(content: 'this is content'));
    }

    public function testAssertContentFails(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->assertContent(function (string $content): void {
            $this->assertSame('does-not-match', $content);
        });

        try {
            $mockRequestBuilder->assert($this->createRealRequest(content: 'this is content'));
        } catch (AssertionFailedError) {
            return;
        }

        $this->fail('Expected assertion error was not thrown');
    }

    public function testAssertThat(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->assertThat(function (RealRequest $realRequest): void {
            $this->assertSame('this is content', $realRequest->getContent());
        });

        $mockRequestBuilder->assert($this->createRealRequest(content: 'this is content'));
    }

    public function testAssertThatFails(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->assertThat(function (RealRequest $realRequest): void {
            $this->assertSame('does-not-match', $realRequest->getContent());
        });

        try {
            $mockRequestBuilder->assert($this->createRealRequest(content: 'this is content'));
        } catch (AssertionFailedError) {
            return;
        }

        $this->fail('Expected assertion error was not thrown');
    }

    public function testEmptyResponsesThrowsException(): void
    {
        $this->expectException(NoResponseMock::class);
        $this->expectExceptionMessage('No response configured for:');

        $mockRequestBuilder = new MockRequestBuilder();

        $mockRequestBuilder->nextResponse();
    }

    public function testNoNextResponseThrowsException(): void
    {
        $this->expectException(NoResponseMock::class);
        $this->expectExceptionMessage('All responses have already been processed for:');

        $mockRequestBuilder = (new MockRequestBuilder())
            ->willRespond(new MockResponseBuilder());

        $mockRequestBuilder->nextResponse();
        $mockRequestBuilder->nextResponse();
    }

    public function testAddAfterAddAlwaysThrowsException(): void
    {
        $this->expectException(AddMockResponseFailed::class);
        $this->expectExceptionMessage('Single response already added, add not possible for:');

        $mockRequestBuilder = (new MockRequestBuilder())
            ->willAlwaysRespond(new MockResponseBuilder())
            ->willRespond(new MockResponseBuilder());
    }

    public function testAddAlwaysAfterAddThrowsException(): void
    {
        $this->expectException(AddMockResponseFailed::class);
        $this->expectExceptionMessage('Response already added, add always not possible for:');

        $mockRequestBuilder = (new MockRequestBuilder())
            ->willRespond(new MockResponseBuilder())
            ->willAlwaysRespond(new MockResponseBuilder());
    }

    public function testSingleResponseIsAlwaysReturned(): void
    {
        $response = new MockResponseBuilder();

        $mockRequestBuilder = (new MockRequestBuilder())
            ->willAlwaysRespond($response);

        $result = $mockRequestBuilder->nextResponse();
        self::assertSame($response, $result);

        $result = $mockRequestBuilder->nextResponse();
        self::assertSame($response, $result);

        $result = $mockRequestBuilder->nextResponse();
        self::assertSame($response, $result);
    }

    public function testMultipleResponsesAreReturned(): void
    {
        $response1 = new MockResponseBuilder();
        $response2 = new RuntimeException('foo');
        $response3 = new MockResponseBuilder();

        $mockRequestBuilder = (new MockRequestBuilder())
            ->willRespond($response1)
            ->willThrow($response2)
            ->willRespond($response3);

        $result = $mockRequestBuilder->nextResponse();
        self::assertSame($response1, $result);

        $result = $mockRequestBuilder->nextResponse();
        self::assertSame($response2, $result);

        $result = $mockRequestBuilder->nextResponse();
        self::assertSame($response3, $result);
    }

    public function testResponseBuilderIsResettable(): void
    {
        $mockRequestBuilder = (new MockRequestBuilder())
            ->willRespond(new MockResponseBuilder())
            ->resetResponses();

        self::assertFalse($mockRequestBuilder->hasResponse());
    }
}
