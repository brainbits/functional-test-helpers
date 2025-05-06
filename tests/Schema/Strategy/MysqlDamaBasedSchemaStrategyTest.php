<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Schema\Strategy;

use ArrayObject;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\MysqlDamaBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function array_values;
use function func_get_arg;
use function func_get_args;
use function Safe\preg_match;
use function str_starts_with;

#[CoversClass(MysqlDamaBasedSchemaStrategy::class)]
final class MysqlDamaBasedSchemaStrategyTest extends TestCase
{
    use SnapshotTrait;

    private MySQLPlatform $platform;
    private MySQLSchemaManager&MockObject $schemaManager;
    private SchemaBuilder $schemaBuilder;

    protected function setUp(): void
    {
        $this->platform = new MySQLPlatform();
        $this->schemaManager = $this->createMock(MySQLSchemaManager::class);

        $this->schemaBuilder = $this->createSchemaBuilder();
        $this->schemaBuilder->foo();
    }

    public function testApplySchema(): void
    {
        /** @phpstan-var ArrayObject<string, mixed[]> $queryLog */
        $queryLog = new ArrayObject();

        $this->schemaManager->expects($this->any())
            ->method('listTableNames')
            ->willReturnCallback(
                static function () use ($queryLog) {
                    $result = [];

                    $queryLog[] = [
                        'function' => 'listTableNames()',
                        'parameters' => func_get_args(),
                        'result' => $result,
                    ];

                    return $result;
                },
            );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->any())
            ->method('quoteIdentifier')
            ->willReturnCallback($this->platform->quoteIdentifier(...));
        $connection->expects($this->any())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_mysql']);
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn($this->platform);
        $connection->expects($this->any())
            ->method('createSchemaManager')
            ->willReturn($this->schemaManager);
        $connection->expects($this->any())
            ->method('executeStatement')
            ->willReturnCallback(
                static function () use ($queryLog): int {
                    $queryLog[] = ['statement' => func_get_arg(0)];

                    return 0;
                },
            );
        $connection->expects($this->any())
            ->method('executeQuery')
            ->willReturnCallback(
                static function () use ($queryLog, $connection) {
                    $query = func_get_arg(0);
                    $parameters = func_get_arg(1);
                    $result = [];

                    $queryLog[] = ['query' => $query, 'parameters' => $parameters, 'result' => $result];

                    return new Result(self::createArrayResult($result), $connection);
                },
            );

        $strategy = new MysqlDamaBasedSchemaStrategy(resetExecutedStatements: true);
        $strategy->applySchema($this->schemaBuilder, $connection);

        $this->assertMatchesArraySnapshot($queryLog->getArrayCopy());
    }

    public function testExistingTablesAreDroppedBeforeCreatingFreshSchema(): void
    {
        /** @phpstan-var ArrayObject<string, mixed[]> $queryLog */
        $queryLog = new ArrayObject();

        $this->schemaManager->expects($this->once())
            ->method('listTableNames')
            ->willReturnCallback(static function () use ($queryLog) {
                $result = ['old_table_1', 'old_table_2'];

                $queryLog[] = ['function' => 'listTableNames()', 'parameters' => func_get_args(), 'result' => $result];

                return $result;
            });

        $this->schemaManager->expects($this->exactly(2))
            ->method('listTableForeignKeys')
            ->willReturnCallback(static function ($tableName) use ($queryLog) {
                $result = [];

                if ($tableName === 'old_table_1') {
                    $result = [
                        new ForeignKeyConstraint([], '', [], 'constraint_1'),
                        new ForeignKeyConstraint([], '', [], 'constraint_2'),
                    ];
                }

                $queryLog[] = [
                    'function' => 'listTableForeignKeys()',
                    'parameters' => func_get_args(),
                    'result' => array_map(static fn ($fk) => $fk->getName(), $result),
                ];

                return $result;
            });

        $this->schemaManager->expects($this->any())
            ->method('dropForeignKey')
            ->willReturnCallback(static function ($tableName) use ($queryLog) {
                $result = [];

                $queryLog[] = ['function' => 'dropForeignKey()', 'parameters' => func_get_args(), 'result' => $result];

                return $result;
            });

        $this->schemaManager->expects($this->any())
            ->method('dropTable')
            ->willReturnCallback(static function ($tableName) use ($queryLog) {
                $result = [];

                $queryLog[] = ['function' => 'dropTable()', 'parameters' => func_get_args(), 'result' => $result];

                return $result;
            });

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->any())
            ->method('quoteIdentifier')
            ->willReturnCallback($this->platform->quoteIdentifier(...));
        $connection->expects($this->any())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_mysql']);
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn($this->platform);
        $connection->expects($this->any())
            ->method('createSchemaManager')
            ->willReturn($this->schemaManager);
        $connection->expects($this->any())
            ->method('executeStatement')
            ->willReturnCallback(
                static function () use ($queryLog): int {
                    $queryLog[] = ['statement' => func_get_arg(0)];

                    return 0;
                },
            );
        $connection->expects($this->any())
            ->method('executeQuery')
            ->willReturnCallback(
                static function () use ($queryLog, $connection) {
                    $query = func_get_arg(0);
                    $parameters = func_get_arg(1);
                    $result = [];

                    if (str_starts_with($query, 'SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')) {
                        // two old tables exists
                        $result = [['name' => 'old_table_1'], ['name' => 'old_table_2']];
                    } elseif (preg_match('/SELECT.*CONSTRAINT_NAME.*old_table_1/', $query)) {
                        // "old_table_1" has two constraints
                        $result = [['name' => 'constraint_1'], ['name' => 'constraint_2']];
                    }

                    $queryLog[] = ['query' => $query, 'parameters' => $parameters, 'result' => $result];

                    return new Result(self::createArrayResult($result), $connection);
                },
            );

        $strategy = new MysqlDamaBasedSchemaStrategy(resetExecutedStatements: true);
        $strategy->applySchema($this->schemaBuilder, $connection);

        $this->assertMatchesArraySnapshot($queryLog->getArrayCopy());
    }

    public function testSchemaIsReadFromCacheIfDatabaseAndCacheExists(): void
    {
        /** @phpstan-var ArrayObject<string, mixed[]> $queryLog */
        $queryLog = new ArrayObject();

        $this->schemaManager->expects($this->any())
            ->method('listTableNames')
            ->willReturnCallback(
                static function () use ($queryLog) {
                    $result = [];

                    $queryLog[] = [
                        'function' => 'listTableNames()',
                        'parameters' => func_get_args(),
                        'result' => $result,
                    ];

                    return $result;
                },
            );

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->any())
            ->method('quoteIdentifier')
            ->willReturnCallback($this->platform->quoteIdentifier(...));
        $connection->expects($this->any())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_mysql']);
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn(new MySQLPlatform());
        $connection->expects($this->any())
            ->method('createSchemaManager')
            ->willReturn($this->schemaManager);
        $connection->expects($this->any())
            ->method('executeStatement')
            ->willReturnCallback(
                static function () use ($queryLog): int {
                    $queryLog[] = ['statement' => func_get_arg(0)];

                    return 0;
                },
            );
        $connection->expects($this->any())
            ->method('executeQuery')
            ->willReturnCallback(
                static function () use ($queryLog, $connection) {
                    $query = func_get_arg(0);
                    $parameters = func_get_arg(1);
                    $result = [];

                    $queryLog[] = ['query' => $query, 'parameters' => $parameters, 'result' => $result];

                    return new Result(self::createArrayResult($result), $connection);
                },
            );

        $strategy = new MysqlDamaBasedSchemaStrategy(resetExecutedStatements: true);
        $strategy->applySchema($this->schemaBuilder, $connection);

        $strategy = new MysqlDamaBasedSchemaStrategy(resetExecutedStatements: false);
        $strategy->applySchema($this->schemaBuilder, $connection);

        $this->assertMatchesArraySnapshot($queryLog->getArrayCopy());
    }

    public function testResetSequences(): void
    {
        /** @phpstan-var ArrayObject<string, mixed[]> $queryLog */
        $queryLog = new ArrayObject();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->any())
            ->method('quoteIdentifier')
            ->willReturnCallback($this->platform->quoteIdentifier(...));
        $connection->expects($this->any())
            ->method('getParams')
            ->willReturn(['driver' => 'pdo_mysql']);
        $connection->expects($this->any())
            ->method('getDatabase')
            ->willReturn('database_name');
        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->willReturn($this->platform);
        $connection->expects($this->any())
            ->method('executeStatement')
            ->willReturnCallback(
                static function () use ($queryLog): int {
                    $queryLog[] = ['statement' => func_get_arg(0)];

                    return 0;
                },
            );
        $connection->expects($this->any())
            ->method('executeQuery')
            ->willReturnCallback(
                static function () use ($queryLog, $connection) {
                    $query = func_get_arg(0);
                    $parameters = func_get_arg(1);

                    $result = [];

                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    if ($query === 'SELECT `TABLE_NAME` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :table AND `AUTO_INCREMENT` > 1') {
                        $result = [['TABLE_NAME' => 'foo']];
                    }

                    $queryLog[] = ['query' => $query, 'parameters' => $parameters, 'result' => $result];

                    return new Result(self::createArrayResult($result), $connection);
                },
            );

        $strategy = new MysqlDamaBasedSchemaStrategy();
        $strategy->resetSequences($connection);

        $this->assertMatchesArraySnapshot($queryLog->getArrayCopy());
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
                $table->addColumn('id', 'integer', ['autoincrement' => true]);
                $table->addColumn('bar', 'string', ['length' => 255]);
            }
        };
    }

    private function snapshotPath(): string
    {
        return __DIR__;
    }

    /** @param mixed[] $result */
    private static function createArrayResult(array $result): ArrayResult
    {
        $columnNames = array_keys($result[0] ?? []);
        $rows = array_map(array_values(...), $result);

        return new ArrayResult($columnNames, $rows);
    }
}
