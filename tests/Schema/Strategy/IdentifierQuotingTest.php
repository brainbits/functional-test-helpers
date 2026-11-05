<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema\Strategy;

use Brainbits\FunctionalTestHelpers\Schema\Strategy\IdentifierQuoting;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(IdentifierQuoting::class)]
final class IdentifierQuotingTest extends TestCase
{
    /** @return iterable<string, array{AbstractPlatform, string, string}> */
    public static function provideIdentifiers(): iterable
    {
        yield 'mysql plain' => [new MySQLPlatform(), 'order', '`order`'];
        yield 'mysql backticks' => [new MySQLPlatform(), '`order`', '`order`'];
        yield 'mysql double quotes' => [new MySQLPlatform(), '"order"', '`order`'];
        yield 'mysql brackets' => [new MySQLPlatform(), '[order]', '`order`'];
        yield 'mysql qualified' => [new MySQLPlatform(), 'db."order"', '`db`.`order`'];
        yield 'sqlite backticks' => [new SQLitePlatform(), '`order`', '"order"'];
    }

    #[DataProvider('provideIdentifiers')]
    public function testQuote(AbstractPlatform $platform, string $identifier, string $expected): void
    {
        $this->assertSame($expected, IdentifierQuoting::quote($platform, $identifier));
    }
}
