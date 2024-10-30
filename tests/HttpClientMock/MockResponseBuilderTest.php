<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockResponseBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function implode;

use const PHP_EOL;

#[CoversClass(MockResponseBuilder::class)]
final class MockResponseBuilderTest extends TestCase
{
    public function testConvertableToStringWithJson(): void
    {
        $builder = (new MockResponseBuilder())
            ->code(200)
            ->header('Content-Language', 'de')
            ->json(['json' => 'content']);

        $parts = [
            'HTTP Code: 200',
            'Content-Language: de',
            'Content-Type: application/json',
            '',
            '{"json":"content"}',
        ];

        self::assertSame(implode(PHP_EOL, $parts), (string) $builder);
    }

    public function testConvertableToStringWithXml(): void
    {
        $builder = (new MockResponseBuilder())
            ->code(200)
            ->header('Content-Language', 'de')
            ->xml('<foo/>');

        $parts = [
            'HTTP Code: 200',
            'Content-Language: de',
            'Content-Type: text/xml',
            '',
            '<foo/>',
        ];

        self::assertSame(implode(PHP_EOL, $parts), (string) $builder);
    }

    public function testConvertableToStringWithHeaders(): void
    {
        $builder = (new MockResponseBuilder())
            ->code(200)
            ->contentType('image/gif')
            ->contentLength(6)
            ->etag('foobar')
            ->content('barbaz');

        $parts = [
            'HTTP Code: 200',
            'Content-Type: image/gif',
            'Content-Length: 6',
            'Etag: foobar',
            '',
            'barbaz',
        ];

        self::assertSame(implode(PHP_EOL, $parts), (string) $builder);
    }
}
