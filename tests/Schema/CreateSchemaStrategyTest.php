<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema;

use Brainbits\FunctionalTestHelpers\Schema\CreateSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\NoSchemaStrategyFound;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\MysqlBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\MysqlDamaBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteFileBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteFileDamaBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteMemoryBasedSchemaStrategy;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Safe\putenv;

final class CreateSchemaStrategyTest extends TestCase
{
    public function testItUsesSqliteMemoryStrategyIfConfiguredByUrl(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['url' => 'sqlite://:memory:']);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(SqliteMemoryBasedSchemaStrategy::class, $applySchema);
    }

    public function testItUsesSqliteMemoryStrategyIfConfiguredByParams(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_sqlite', 'memory' => true]);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(SqliteMemoryBasedSchemaStrategy::class, $applySchema);
    }

    public function testItUsesSqliteFileStrategyIfConfiguredByUrl(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['url' => 'sqlite:///tmp/my.db', 'path' => '/tmp/my.db']);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(SqliteFileBasedSchemaStrategy::class, $applySchema);
    }

    public function testItUsesSqliteStrategyIfConfiguredByParams(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_sqlite', 'path' => '/tmp/my.db']);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(SqliteFileBasedSchemaStrategy::class, $applySchema);
    }

    public function testItUsesSqliteDamaFileStrategyIfConfiguredByUrl(): void
    {
        $driver = $this->createStub(StaticDriver::class);
        StaticDriver::setKeepStaticConnections(true);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['url' => 'sqlite:///tmp/my.db', 'path' => '/tmp/my.db']);
        $connection->expects($this->once())
            ->method('getDriver')
            ->willReturn($driver);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(SqliteFileDamaBasedSchemaStrategy::class, $applySchema);
    }

    public function testItUsesSqliteDamaStrategyIfConfiguredByParams(): void
    {
        $driver = $this->createStub(StaticDriver::class);
        StaticDriver::setKeepStaticConnections(true);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_sqlite', 'path' => '/tmp/my.db']);
        $connection->expects($this->once())
            ->method('getDriver')
            ->willReturn($driver);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(SqliteFileDamaBasedSchemaStrategy::class, $applySchema);
    }

    public function testItUsesMysqlStrategyIfDriverIsSelected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_mysql']);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(MysqlBasedSchemaStrategy::class, $applySchema);
    }

    public function testItUsesMysqlDamaStrategyIfStaticConnectionIsActive(): void
    {
        $driver = $this->createStub(StaticDriver::class);
        StaticDriver::setKeepStaticConnections(true);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_mysql']);
        $connection->expects($this->once())
            ->method('getDriver')
            ->willReturn($driver);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(MysqlDamaBasedSchemaStrategy::class, $applySchema);
    }

    public function testItUsesMysqlStrategyIfStaticConnectionIsActive(): void
    {
        $driver = $this->createStub(StaticDriver::class);
        StaticDriver::setKeepStaticConnections(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_mysql']);
        $connection->expects($this->once())
            ->method('getDriver')
            ->willReturn($driver);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(MysqlBasedSchemaStrategy::class, $applySchema);
    }

    public function testItThrowsException(): void
    {
        $this->expectException(NoSchemaStrategyFound::class);
        $this->expectExceptionMessage('No apply schema strategy found for connection parameters: {"key":"value"}');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['key' => 'value']);

        $applySchema = (new CreateSchemaStrategy())($connection);

        $this->assertInstanceOf(SqliteFileBasedSchemaStrategy::class, $applySchema);
    }

    /** @return iterable<string, array{mixed[], class-string}> */
    public static function provideParams(): iterable
    {
        yield 'sqlite3 memory' => [['driver' => 'sqlite3', 'memory' => true], SqliteMemoryBasedSchemaStrategy::class];
        yield 'sqlite3 file' => [['driver' => 'sqlite3', 'path' => '/tmp/my.db'], SqliteFileBasedSchemaStrategy::class];
        yield 'mysqli' => [['driver' => 'mysqli'], MysqlBasedSchemaStrategy::class];
    }

    /**
     * @param mixed[]      $params
     * @param class-string $expectedStrategy
     */
    #[DataProvider('provideParams')]
    public function testItDetectsDrivers(array $params, string $expectedStrategy): void
    {
        StaticDriver::setKeepStaticConnections(false);

        $connection = $this->createStub(Connection::class);
        $connection->method('getParams')->willReturn($params);

        $this->assertInstanceOf($expectedStrategy, (new CreateSchemaStrategy())($connection));
    }

    public function testItFallsBackToThePlatform(): void
    {
        StaticDriver::setKeepStaticConnections(false);

        $connection = $this->createStub(Connection::class);
        $connection->method('getParams')->willReturn(['driverClass' => 'App\\Driver', 'path' => '/tmp/my.db']);
        $connection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());

        $this->assertInstanceOf(SqliteFileBasedSchemaStrategy::class, (new CreateSchemaStrategy())($connection));
    }

    public function testStrategyCanBeForced(): void
    {
        putenv('SCHEMA_STRATEGY=sqlite-memory');

        try {
            $connection = $this->createStub(Connection::class);
            $connection->method('getParams')->willReturn(['driver' => 'pdo_mysql']);

            $this->assertInstanceOf(SqliteMemoryBasedSchemaStrategy::class, (new CreateSchemaStrategy())($connection));
        } finally {
            putenv('SCHEMA_STRATEGY');
        }
    }

    public function testExceptionDoesNotContainThePassword(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getParams')->willReturn(['driver' => 'pdo_pgsql', 'password' => 's3cret']);

        try {
            (new CreateSchemaStrategy())($connection);
        } catch (NoSchemaStrategyFound $e) {
            $this->assertStringNotContainsString('s3cret', $e->getMessage());
            $this->assertStringContainsString('"password":"***"', $e->getMessage());

            return;
        }

        $this->fail('No exception thrown');
    }
}
