<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

#[CoversClass(RealRequestFactory::class)]
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
        $body = $this->getMockBuilder(StreamInterface::class)->getMock();
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
}
