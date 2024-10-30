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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateSchemaStrategy::class)]
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
        $driver = $this->createMock(StaticDriver::class);
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
        $driver = $this->createMock(StaticDriver::class);
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
        $driver = $this->createMock(StaticDriver::class);
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
        $driver = $this->createMock(StaticDriver::class);
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
}
