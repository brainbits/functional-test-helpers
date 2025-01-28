<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

use function sprintf;

#[CoversClass(MockRequestBuilderFactory::class)]
final class MockRequestBuilderFactoryTest extends TestCase
{
    private readonly MockRequestBuilderFactory $mockRequestBuilderFactory;

    public function setUp(): void
    {
        $this->mockRequestBuilderFactory = new MockRequestBuilderFactory();
    }

    public function testBuildsRequestWithoutBody(): void
    {
        $options = [
            'headers' => ['Content-Type: application/json'],
            'json' => ['foo' => 'bar'],
        ];
        $request = ($this->mockRequestBuilderFactory)('POST', 'https://service.com', $options);

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

        $request = ($this->mockRequestBuilderFactory)('POST', 'https://service.com', $options);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.com', $request->getUri());
        self::assertSame(['foo' => 'bar'], $request->getJson());
        self::assertTrue($request->isJson());
    }

    public function testBuildsRequestWithCallableInBody(): void
    {
        $size = 1;
        $body = $this->getMockBuilder(StreamInterface::class)->getMock();
        $body
            ->expects(self::once())
            ->method('read')
            ->with($size)
            ->willReturn('{"foo": "bar"}');

        $options = [
            'headers' => [
                sprintf('Content-Length: %d', $size),
                'Content-Type: application/json',
            ],
            'body' => static fn (int $size) => $body->read($size),
        ];

        $request = ($this->mockRequestBuilderFactory)('POST', 'https://service.com', $options);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://service.com', $request->getUri());
        self::assertSame(['foo' => 'bar'], $request->getJson());
        self::assertTrue($request->isJson());
    }
}
