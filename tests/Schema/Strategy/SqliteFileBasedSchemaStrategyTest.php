<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema\Strategy;

use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteFileBasedSchemaStrategy;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function func_get_arg;
use function Safe\file_put_contents;
use function Safe\tempnam;
use function Safe\unlink;
use function sys_get_temp_dir;

use const FILE_APPEND;

#[CoversClass(SqliteFileBasedSchemaStrategy::class)]
final class SqliteFileBasedSchemaStrategyTest extends TestCase
{
    private string|null $databasePath = null;
    private string|null $cachePath = null;

    protected function setUp(): void
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'SqliteFileBasedApplySchemaTest_') . '.db';
        $this->cachePath = $this->databasePath . '.cache';
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== null && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if ($this->cachePath !== null && file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    public function testApplySchema(): void
    {
        $schemaBuilder = $this->createSchemaBuilder();
        $schemaBuilder->foo();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['path' => $this->databasePath]);
        $connection->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SqlitePlatform());
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with('CREATE TABLE foo (bar VARCHAR(255) NOT NULL)')
            ->willReturnCallback(fn () => file_put_contents($this->databasePath, func_get_arg(0), FILE_APPEND));

        $strategy = new SqliteFileBasedSchemaStrategy(resetExecutedStatements: true);
        $strategy->applySchema($schemaBuilder, $connection);

        $this->assertStringEqualsFile($this->databasePath, 'CREATE TABLE foo (bar VARCHAR(255) NOT NULL)');
        $this->assertFileEquals($this->databasePath, $this->cachePath);
    }

    public function testSchemaIsNotReadFromCacheIfDatabaseIsMissing(): void
    {
        $schemaBuilder = $this->createSchemaBuilder();
        $schemaBuilder->foo();

        file_put_contents($this->cachePath, 'cached');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getParams')
            ->willReturn(['path' => $this->databasePath]);
        $connection->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new SqlitePlatform());
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with('CREATE TABLE foo (bar VARCHAR(255) NOT NULL)')
            ->willReturnCallback(fn () => file_put_contents($this->databasePath, func_get_arg(0), FILE_APPEND));

        $strategy = new SqliteFileBasedSchemaStrategy(resetExecutedStatements: true);
        $strategy->applySchema($schemaBuilder, $connection);

        $this->assertStringEqualsFile($this->databasePath, 'CREATE TABLE foo (bar VARCHAR(255) NOT NULL)');
        $this->assertFileEquals($this->databasePath, $this->cachePath);
    }

    public function testSchemaIsReadFromCacheIfDatabaseAndCacheExists(): void
    {
        $schemaBuilder = $this->createSchemaBuilder();
        $schemaBuilder->foo();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('getParams')
            ->willReturn(['path' => $this->databasePath]);
        $connection->expects($this->exactly(2))
            ->method('getDatabasePlatform')
            ->willReturn(new SqlitePlatform());
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with('CREATE TABLE foo (bar VARCHAR(255) NOT NULL)')
            ->willReturnCallback(fn () => file_put_contents($this->databasePath, func_get_arg(0), FILE_APPEND));

        $strategy = new SqliteFileBasedSchemaStrategy(resetExecutedStatements: true);
        $strategy->applySchema($schemaBuilder, $connection);
        $strategy = new SqliteFileBasedSchemaStrategy(resetExecutedStatements: false);
        $strategy->applySchema($schemaBuilder, $connection);

        $this->assertStringEqualsFile($this->databasePath, 'CREATE TABLE foo (bar VARCHAR(255) NOT NULL)');
        $this->assertFileEquals($this->databasePath, $this->cachePath);
    }

    private function createSchemaBuilder(): SchemaBuilder
    {
        return new class implements SchemaBuilder {
            private Schema $schema;

            public function __construct()
            {
                $this->schema = new Schema();
            }

            public static function create(): SchemaBuilder
            {
                return new self();
            }

            public function getSchema(): Schema
            {
                return $this->schema;
            }

            public function foo(): void
            {
                $table = $this->schema->createTable('foo');
                $table->addColumn('bar', 'string', ['length' => 255]);
            }
        };
    }
}
