<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

use function array_shift;

final class RealRequestFactoryTest extends TestCase
{
    private readonly RealRequestFactory $realRequestFactory;

    public function setUp(): void
    {
        $this->realRequestFactory = new RealRequestFactory();
    }

    public function testBuildsRequestWithoutBody(): void
    {
        $options = [
            'headers' => ['Content-Type: application/json'],
            'json' => ['foo' => 'bar'],
        ];
        $request = ($this->realRequestFactory)('POST', 'https://service.com', $options);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.com', $request->getUri());
        self::assertSame(['foo' => 'bar'], $request->getJson());
    }

    public function testBuildsRequestWithMultipleQueryParams(): void
    {
        $options = ['query_params' => ['name' => ['abc', 'def']]];
        $request = ($this->realRequestFactory)('POST', 'https://service.com', $options);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.com', $request->getUri());
        self::assertSame(['name' => ['abc', 'def']], $request->getQueryParams());
    }

    public function testBuildsRequestWithMultipleQueryParamsInUri(): void
    {
        $options = [];
        $request = ($this->realRequestFactory)('POST', 'https://service.com?name[]=abc&name[]=def', $options);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.com', $request->getUri());
        self::assertSame(['name' => ['abc', 'def']], $request->getQueryParams());
    }

    public function testBuildsRequestWithJsonInBody(): void
    {
        $options = [
            'headers' => [
                'Content-Length: 1',
                'Content-Type: application/json',
            ],
            'body' => '{"foo": "bar"}',
        ];

        $request = ($this->realRequestFactory)('POST', 'https://service.com', $options);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.com', $request->getUri());
        self::assertSame(['foo' => 'bar'], $request->getJson());
    }

    public function testBuildsRequestWithCallableInBody(): void
    {
        $body = $this->createStub(StreamInterface::class);
        $body->method('read')
            ->willReturn('{"foo": "bar"}');

        $options = [
            'headers' => [
                'Content-Length: 1',
                'Content-Type: application/json',
            ],
            'body' => static fn (int $size) => $body->read($size),
        ];

        $request = ($this->realRequestFactory)('POST', 'https://service.com', $options);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.com', $request->getUri());
        self::assertSame(['foo' => 'bar'], $request->getJson());
    }

    public function testBuildsRequestWithFormUrlEncodedInBody(): void
    {
        $options = [
            'headers' => [
                'Content-Length: 1',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            'body' => 'foo=bar',
        ];

        $request = ($this->realRequestFactory)('POST', 'https://service.com', $options);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.com', $request->getUri());
        self::assertSame(['foo' => 'bar'], $request->getRequestParams());
    }

    public function testBuildsRequestWithMultiPartInBody(): void
    {
        $options = [
            'headers' => [
                'Content-Length: 1',
                'Content-Type: multipart/form-data; boundary=12345',
            ],
            'body' => <<<'BODY'
                --12345
                Content-Disposition: form-data; name="key"
                
                content
                --12345--
                BODY,
        ];

        $request = ($this->realRequestFactory)('POST', 'https://service.com', $options);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.com', $request->getUri());
        self::assertEquals(
            [
                'key' => [
                    'name' => 'key',
                    'filename' => null,
                    'mimetype' => 'application/octet-stream',
                    'content' => 'content',
                ],
            ],
            $request->getMultiparts(),
        );
    }

    public function testScalarAndArrayQueryParamWithTheSameNameDoNotCrash(): void
    {
        $request = ($this->realRequestFactory)('GET', 'https://service.com?a=1&a[]=2', []);

        self::assertSame(['a' => ['1', '2']], $request->getQueryParams());
    }

    public function testFormBodyKeyWithoutValueDoesNotCrash(): void
    {
        $options = [
            'headers' => ['Content-Type: application/x-www-form-urlencoded'],
            'body' => 'a=1&flag',
        ];
        $request = ($this->realRequestFactory)('POST', 'https://service.com', $options);

        self::assertSame(['a' => '1', 'flag' => ''], $request->getRequestParams());
    }

    public function testStreamedBodyIsReadCompletely(): void
    {
        $chunks = ['{"foo":', ' "bar"}', ''];
        $options = [
            'headers' => ['Content-Type: application/json'],
            'body' => static function () use (&$chunks): string {
                return array_shift($chunks) ?? '';
            },
        ];

        $request = ($this->realRequestFactory)('POST', 'https://service.com', $options);

        self::assertSame('{"foo": "bar"}', $request->getContent());
        self::assertSame(['foo' => 'bar'], $request->getJson());
    }

    public function testGeneratorBodyIsReadCompletely(): void
    {
        $generator = (static function () {
            yield 'a';
            yield 'b';
        })();

        $request = ($this->realRequestFactory)('POST', 'https://service.com', ['body' => $generator]);

        self::assertSame('ab', $request->getContent());
    }

    public function testEmptyAndScalarJsonBodies(): void
    {
        $options = ['headers' => ['Content-Type: application/json'], 'body' => ''];
        self::assertNull(($this->realRequestFactory)('POST', 'https://service.com', $options)->getJson());

        $options = ['headers' => ['Content-Type: application/json'], 'body' => '"abc"'];
        $request = ($this->realRequestFactory)('POST', 'https://service.com', $options);
        self::assertNull($request->getJson());
        self::assertSame('"abc"', $request->getContent());
    }

    public function testHeaderValuesWithColons(): void
    {
        $options = ['headers' => ['X-Info: a: b', 'X-Multi: a', 'X-Multi: b']];

        $request = ($this->realRequestFactory)('GET', 'https://service.com', $options);

        self::assertSame('a: b', $request->getHeader('X-Info'));
        self::assertSame('b', $request->getHeader('X-Multi'));
        self::assertTrue($request->hasHeader('X-Info'));
    }
}
