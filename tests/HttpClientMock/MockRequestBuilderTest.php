<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\AddMockResponseFailed;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\InvalidMockRequest;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\NoResponseMock;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockResponseBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @covers \Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder */
final class MockRequestBuilderTest extends TestCase
{
    public function testWithoutAnythingSpecifiedARequestIsEmpty(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();

        self::assertTrue($mockRequestBuilder->isEmpty());
    }

    public function testWithRequestParametersARequestIsNotEmpty(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->requestParam('one', '1');

        self::assertFalse($mockRequestBuilder->isEmpty());
    }

    public function testWithoutContentNoRequestParametersExists(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();

        self::assertFalse($mockRequestBuilder->hasRequestParams());
    }

    public function testWithNotDecodableContentNoRequestParametersExists(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->content('no form data');

        self::assertFalse($mockRequestBuilder->hasRequestParams());
    }

    public function testWithJsonContentNoRequestParametersExists(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->json(['value' => 'key=value']);

        self::assertFalse($mockRequestBuilder->hasRequestParams());
    }

    public function testRequestMayHaveOneRequestParameter(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->requestParam('one', '1');

        self::assertTrue($mockRequestBuilder->hasRequestParams());
        self::assertSame(['one' => '1'], $mockRequestBuilder->getRequestParams());
    }

    public function testRequestMayHaveMultipleRequestParameters(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->requestParam('one', '1');
        $mockRequestBuilder->requestParam('two', '2');
        $mockRequestBuilder->requestParam('three', '3');

        self::assertTrue($mockRequestBuilder->hasRequestParams());
        self::assertSame(['one' => '1', 'two' => '2', 'three' => '3'], $mockRequestBuilder->getRequestParams());
    }

    public function testRequestParameterValuesMayBeEmpty(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->requestParam('one', '1');
        $mockRequestBuilder->requestParam('empty', '');
        $mockRequestBuilder->requestParam('three', '3');

        self::assertTrue($mockRequestBuilder->hasRequestParams());
        self::assertSame(['one' => '1', 'empty' => '', 'three' => '3'], $mockRequestBuilder->getRequestParams());
    }

    public function testUriIsOptional(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->uri(null);

        self::assertFalse($mockRequestBuilder->hasUri(), 'no uri');
        self::assertFalse($mockRequestBuilder->hasQueryParams(), 'no query parameters');
    }

    public function testParsesQueryParamsInUri(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->uri('http://example.org?one=1&two=2');

        self::assertSame(['one' => '1', 'two' => '2'], $mockRequestBuilder->getQueryParams());
    }

    public function testSupportsEncodesQueryParamsInUri(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->uri('http://example.org?space=%20&quote=%22');

        self::assertSame(['space' => ' ', 'quote' => '"'], $mockRequestBuilder->getQueryParams());
    }

    public function testSupportsQueryParams(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->queryParam('one', '1');
        $mockRequestBuilder->queryParam('two', '2');
        $mockRequestBuilder->queryParam('three', '%s %s %s', 'a', 'b', 'c');

        self::assertSame(['one' => '1', 'two' => '2', 'three' => 'a b c'], $mockRequestBuilder->getQueryParams());
    }

    public function testIgnoresEmptyQueryString(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->uri('http://example.org?');

        self::assertNull($mockRequestBuilder->getQueryParams());
        self::assertSame('http://example.org', $mockRequestBuilder->getUri());
    }

    public function testEmptyResponsesThrowsException(): void
    {
        $this->expectException(NoResponseMock::class);
        $this->expectExceptionMessage('No response configured for:');

        $mockRequestBuilder = (new MockRequestBuilder());

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

    public function testXmlStringsAreDetectedAsXml(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->content('<root><first>abc</first></root>');

        self::assertTrue($mockRequestBuilder->isXml());
        self::assertFalse($mockRequestBuilder->isEmpty());
        self::assertFalse($mockRequestBuilder->isJson());
    }

    public function testXmlStringsAreAccessibleAsSimpleXmlObjects(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->content('<root><first>abc</first></root>');

        self::assertSame('abc', (string) $mockRequestBuilder->getXml()->first);
    }

    public function testXmlStringsWithNamespacesAreAccessibleAsSimpleXmlObjects(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->content('<root xmlns="http://example.org/xml"><first>abc</first></root>');

        $xml = $mockRequestBuilder->getXml(['x' => 'http://example.org/xml']);

        self::assertSame('abc', (string) $xml->xpath('//x:first')[0]);
    }

    public function testWithJsonContent(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->json(['value' => 'key=value']);

        self::assertTrue($mockRequestBuilder->isJson());
        self::assertSame(['value' => 'key=value'], $mockRequestBuilder->getJson());
    }

    public function testWithInvalidXmlThrowsException(): void
    {
        $this->expectException(InvalidMockRequest::class);
        $this->expectExceptionMessage('No valid xml: foo');

        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->xml('foo');
    }

    public function testWithXmlContent(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->xml('<foo/>');

        self::assertTrue($mockRequestBuilder->isXml());
        self::assertXmlStringEqualsXmlString('<?xml version="1.0"?><foo/>', $mockRequestBuilder->getXml()->saveXML());
    }

    public function testWithBasicAuthentication(): void
    {
        $mockRequestBuilder = new MockRequestBuilder();
        $mockRequestBuilder->basicAuthentication('username', 'password');

        $this->assertSame('Basic dXNlcm5hbWU6cGFzc3dvcmQ=', $mockRequestBuilder->getHeader('Authorization'));
    }
}
