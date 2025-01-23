<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriParams;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UriParams::class)]
final class UriParamsTest extends TestCase
{
    public function testParamsAreEmptyOnCreation(): void
    {
        $uriParams = new UriParams();

        self::assertCount(0, $uriParams);
    }

    public function testParamsCanBeSetViaConstructor(): void
    {
        $uriParams = new UriParams(['a' => '1', 'b' => '2']);

        self::assertCount(2, $uriParams);
    }

    public function testParamsCanBeChecked(): void
    {
        $uriParams = new UriParams(['a' => '1', 'b' => '2']);

        self::assertTrue($uriParams->has('a'));
        self::assertFalse($uriParams->has('c'));
    }

    public function testParamsCanBeRetrieved(): void
    {
        $uriParams = new UriParams(['a' => '1', 'b' => '2']);

        self::assertSame('1', $uriParams->get('a'));
        self::assertNull($uriParams->get('c'));
    }

    public function testParamsCanBeSet(): void
    {
        $uriParams = new UriParams(['a' => '1', 'b' => '2']);

        self::assertFalse($uriParams->has('c'));
        self::assertNull($uriParams->get('c'));

        $uriParams->set('c', '3');

        self::assertTrue($uriParams->has('c'));
        self::assertSame('3', $uriParams->get('c'));
    }

    public function testParamsCanBeRetrievedAsArray(): void
    {
        $uriParams = new UriParams(['a' => '1', 'b' => '2']);

        self::assertSame(['a' => '1', 'b' => '2'], $uriParams->toArray());
    }

    public function testParamsCanBeRetrievedAsJson(): void
    {
        $uriParams = new UriParams(['a' => '1', 'b' => '2']);

        self::assertJsonStringEqualsJsonString('{"a":"1","b":"2"}', $uriParams->toJson());
    }

    public function testParamsCanBeReplacedOnString(): void
    {
        $uriParams = new UriParams(['a' => '1', 'b' => '2']);

        self::assertSame('foo 1 2', $uriParams->replace('foo {a} {b}'));
    }
}
