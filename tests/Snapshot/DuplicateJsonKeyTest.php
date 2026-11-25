<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Snapshot;

use Brainbits\FunctionalTestHelpers\Snapshot\DuplicateJsonKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DuplicateJsonKey::class)]
final class DuplicateJsonKeyTest extends TestCase
{
    /** @return iterable<string, array{string, string|null}> */
    public static function provideJson(): iterable
    {
        yield 'unique keys' => ['{"a":1,"b":{"a":2},"c":[{"a":3},{"a":4}]}', null];
        yield 'duplicate key' => ['{"a":1,"b":2,"a":3}', 'a'];
        yield 'duplicate key in nested object' => ['{"items":{"x":1,"x":2}}', 'x'];
        yield 'duplicate key in object in list' => ['[{"id":1},{"id":2,"id":3}]', 'id'];
        yield 'same string as key and value' => ['{"a":"a","b":"a"}', null];
        yield 'escaped quote in string' => ['{"a\"b":"x\"y","c":"a\"b"}', null];
        yield 'escaped duplicate key' => ['{"ä":1,"ä":2}', 'ä'];
        yield 'list of strings' => ['{"a":["a","a"]}', null];
        yield 'formatted' => ["{\n    \"a\": 1,\n    \"a\": 2\n}", 'a'];
    }

    #[DataProvider('provideJson')]
    public function testFind(string $json, string|null $duplicate): void
    {
        self::assertSame($duplicate, DuplicateJsonKey::find($json));
    }
}
