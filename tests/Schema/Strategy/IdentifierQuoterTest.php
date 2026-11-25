<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema\Strategy;

use Brainbits\FunctionalTestHelpers\Schema\Strategy\IdentifierQuoter;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentifierQuoter::class)]
final class IdentifierQuoterTest extends TestCase
{
    /** @return iterable<string, array{AbstractPlatform, string, string}> */
    public static function provideIdentifiers(): iterable
    {
        yield 'plain, sqlite' => [new SQLitePlatform(), 'foo', '"foo"'];
        yield 'plain, mysql' => [new MySQLPlatform(), 'foo', '`foo`'];
        yield 'keyword' => [new SQLitePlatform(), 'order', '"order"'];
        yield 'space and dollar' => [new SQLitePlatform(), 'Foo Bar$Baz', '"Foo Bar$Baz"'];
        yield 'square brackets' => [new SQLitePlatform(), '[Foo Bar$Baz]', '"Foo Bar$Baz"'];
        yield 'double quotes' => [new SQLitePlatform(), '"foo"', '"foo"'];
        yield 'backticks, mysql' => [new MySQLPlatform(), '`foo`', '`foo`'];
        yield 'qualified' => [new SQLitePlatform(), 'foo.bar', '"foo"."bar"'];
        yield 'qualified, brackets' => [new MySQLPlatform(), '[foo].[bar]', '`foo`.`bar`'];
    }

    #[DataProvider('provideIdentifiers')]
    public function testItQuotesEachPart(AbstractPlatform $platform, string $identifier, string $expected): void
    {
        self::assertSame($expected, IdentifierQuoter::quote($platform, $identifier));
    }
}
