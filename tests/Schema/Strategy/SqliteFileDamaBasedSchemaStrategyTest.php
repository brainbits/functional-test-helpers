<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema\Strategy;

use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteFileDamaBasedSchemaStrategy;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SqlitePlatform;
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

#[CoversClass(SqliteFileDamaBasedSchemaStrategy::class)]
final class SqliteFileDamaBasedSchemaStrategyTest extends TestCase
{
    private string|null $databasePath = null;

    protected function setUp(): void
    {
        $this->databasePath = tempnam(sys_get_temp_dir(), 'SqliteFileBasedApplySchemaTest_') . '.db';
    }

    protected function tearDown(): void
    {
        if ($this->databasePath === null || !file_exists($this->databasePath)) {
            return;
        }

        unlink($this->databasePath);
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

        $strategy = new SqliteFileDamaBasedSchemaStrategy();
        $strategy->applySchema($schemaBuilder, $connection);

        $this->assertStringEqualsFile($this->databasePath, 'CREATE TABLE foo (bar VARCHAR(255) NOT NULL)');
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
                $table->addColumn('bar', 'string');
            }
        };
    }
}
