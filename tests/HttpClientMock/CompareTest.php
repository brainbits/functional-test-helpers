<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Compare;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Compare::class)]
final class CompareTest extends TestCase
{
    public function testCompareScalar(): void
    {
        $compare = new Compare();

        $this->assertTrue($compare(1, 1));
        $this->assertTrue($compare('foo', 'foo'));
        $this->assertTrue($compare(5.8, 5.8));

        $this->assertFalse($compare(1, 2));
        $this->assertFalse($compare(1, 1.0));
        $this->assertFalse($compare(1, '1'));
        $this->assertFalse($compare('foo', 'bar'));
    }

    public function testCompareSimpleArray(): void
    {
        $compare = new Compare();

        $this->assertTrue($compare(null, null));
        $this->assertTrue($compare([1, 2, 3], [1, 2, 3]));
        $this->assertTrue($compare(['foo', 'bar', 'baz'], ['foo', 'bar', 'baz']));
        $this->assertTrue($compare([], []));

        $this->assertFalse($compare([1, 2, 3], [1, 2, 4]));
        $this->assertFalse($compare([1, 2, 3], ['1', 2, 3]));
        $this->assertFalse($compare(['foo', 'bar', 'baz'], ['a', 'b', 'c']));
        $this->assertFalse($compare([], [1]));
        $this->assertFalse($compare([], null));
    }

    public function testCompareSimpleAssociativeArray(): void
    {
        $compare = new Compare();

        $this->assertTrue($compare(['foo' => 1], ['foo' => 1]));
        $this->assertTrue($compare(['foo' => 'bar'], ['foo' => 'bar']));

        $this->assertFalse($compare(['foo' => 1], ['baz' => 1]));
        $this->assertFalse($compare(['foo' => 'bar'], ['baz' => 'bar']));
    }

    public function testCompareNestedArray(): void
    {
        $compare = new Compare();

        $this->assertTrue($compare(
            [[1, 2, 3], [4, 5, 6]],
            [[1, 2, 3], [4, 5, 6]],
        ));
        $this->assertTrue($compare(
            [['foo', 'bar', 'baz'], ['a', 'b', 'c']],
            [['foo', 'bar', 'baz'], ['a', 'b', 'c']],
        ));
        $this->assertTrue($compare(
            [['foo', 'bar', 'baz'], ['a', 'b', 'c']],
            [['a', 'b', 'c'], ['foo', 'bar', 'baz']],
        ));
        $this->assertFalse($compare(
            [[1, 2, 3], [4, 5, 6]],
            [[1, 2, 3]],
        ));
        $this->assertFalse($compare(
            [[1, 2, 3], [4, 5, 6]],
            [[1, 2, 3], [4, 5, 7]],
        ));
        $this->assertFalse($compare(
            ['a' => [1, 2, 3], 'b' => [4, 5, 6]],
            ['b' => [1, 2, 3], 'a' => [4, 5, 7]],
        ));
        $this->assertFalse($compare(
            ['a' => [1, 2, 3], 'b' => [4, 5, 6]],
            ['a' => [1, 2, 3], 'b' => [4, 5, 7], 'c' => []],
        ));
    }
}
