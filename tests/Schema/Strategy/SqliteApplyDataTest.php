<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema\Strategy;

use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\IdentifierQuoting;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SchemaStatements;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteFileBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteMemoryBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteTables;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

use function array_keys;
use function dirname;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Applies schemas to real SQLite connections.
 */
#[CoversClass(IdentifierQuoting::class)]
#[CoversClass(SchemaStatements::class)]
#[CoversClass(SqliteFileBasedSchemaStrategy::class)]
#[CoversClass(SqliteMemoryBasedSchemaStrategy::class)]
#[CoversClass(SqliteTables::class)]
final class SqliteApplyDataTest extends TestCase
{
    public function testSecondFixtureOnTheSameMemoryConnection(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $strategy = new SqliteMemoryBasedSchemaStrategy();
        $strategy->applySchema($this->schemaBuilder(['id']), $connection);
        $strategy->applySchema($this->schemaBuilder(['id']), $connection);

        $this->assertSame(['item'], $connection->createSchemaManager()->listTableNames());
    }

    public function testSqliteFileStrategyRebuildsTheDatabaseForChangedTableDefinitions(): void
    {
        $directory = sys_get_temp_dir() . '/functional-test-helpers-' . uniqid() . '/nested/dir';
        $path = $directory . '/test.db';
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);

        try {
            $strategy = new SqliteFileBasedSchemaStrategy();
            $strategy->applySchema($this->schemaBuilder(['id']), $connection);
            $strategy->applySchema($this->schemaBuilder(['id', 'name']), $connection);

            $columns = array_keys($connection->createSchemaManager()->listTableColumns('item'));
            $this->assertSame(['id', 'name'], $columns);
        } finally {
            $connection->close();
            (new Filesystem())->remove(dirname($directory, 2));
        }
    }

    /** @param list<string> $columns */
    private function schemaBuilder(array $columns): SchemaBuilder
    {
        $schema = new Schema();
        $table = $schema->createTable('item');
        foreach ($columns as $column) {
            $table->addColumn($column, $column === 'id' ? 'integer' : 'string', ['notnull' => false]);
        }

        $schemaBuilder = $this->createStub(SchemaBuilder::class);
        $schemaBuilder->method('getSchema')->willReturn($schema);

        return $schemaBuilder;
    }
}
