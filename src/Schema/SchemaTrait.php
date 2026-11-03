<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema;

use Brainbits\FunctionalTestHelpers\Schema\Strategy\IdentifierQuoting;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SchemaStrategy;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

use function getenv;
use function implode;
use function is_bool;
use function Safe\json_encode;
use function sprintf;

// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

/** @mixin TestCase */
trait SchemaTrait
{
    protected static bool $isSchemaDatabaseClean = false;

    private SchemaStrategy|null $schemaStrategy = null;
    private Connection|null $schemaStrategyConnection = null;

    final protected function fixtureFromConnection(
        Connection $connection,
        SchemaBuilder $schemaBuilder,
        DataBuilder $dataBuilder,
        callable $buildData,
    ): void {
        $buildData($dataBuilder);

        $this->schemaStrategy = $this->createSchemaStrategy($connection);

        if (!getenv('USE_PRE_INITIALIZED_SCHEMA')) {
            $this->schemaStrategy->applySchema($schemaBuilder, $connection);
        }

        if (!self::$isSchemaDatabaseClean) {
            $this->schemaStrategy->deleteData($connection);
            $this->schemaStrategy->resetSequences($connection);
        }

        try {
            $this->schemaStrategy->applyData($dataBuilder, $connection);
        } finally {
            self::$isSchemaDatabaseClean = false;
        }

        $this->schemaStrategyConnection = $connection;
    }

    final protected function fixtureFromNewConnection(
        SchemaBuilder $schemaBuilder,
        DataBuilder $dataBuilder,
        callable $buildData,
    ): Connection {
        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
        );

        $this->fixtureFromConnection($connection, $schemaBuilder, $dataBuilder, $buildData);

        return $connection;
    }

    final protected function cleanupFixture(): void
    {
        // without a fixture nothing was cleaned, so the database must not be marked clean
        if ($this->schemaStrategyConnection === null || $this->schemaStrategy === null) {
            return;
        }

        $this->schemaStrategy->deleteData($this->schemaStrategyConnection);
        $this->schemaStrategy->resetSequences($this->schemaStrategyConnection);

        self::$isSchemaDatabaseClean = true;
    }

    /**
     * Asserts the number of rows in a table of the fixture connection.
     */
    protected function assertTableRowCount(int $expected, string $table, string $message = ''): void
    {
        $connection = $this->getFixtureConnection();
        $quotedTable = IdentifierQuoting::quote($connection->getDatabasePlatform(), $table);

        self::assertSame(
            $expected,
            (int) $connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $quotedTable)),
            $message !== '' ? $message : sprintf('Table %s does not contain %d rows.', $table, $expected),
        );
    }

    /**
     * Asserts that a table of the fixture connection contains a row with the given column values.
     *
     * @param array<string, scalar|null> $values
     */
    protected function assertTableContains(string $table, array $values, string $message = ''): void
    {
        $connection = $this->getFixtureConnection();
        $platform = $connection->getDatabasePlatform();

        $conditions = [];
        $parameters = [];
        foreach ($values as $column => $value) {
            $quotedColumn = IdentifierQuoting::quote($platform, $column);

            if ($value === null) {
                $conditions[] = sprintf('%s IS NULL', $quotedColumn);

                continue;
            }

            $conditions[] = sprintf('%s = ?', $quotedColumn);
            // PDO binds false as '', which matches neither 0 nor a boolean column
            $parameters[] = is_bool($value) ? (int) $value : $value;
        }

        $sql = sprintf(
            'SELECT COUNT(*) FROM %s%s',
            IdentifierQuoting::quote($platform, $table),
            $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '',
        );

        if ($message === '') {
            $message = sprintf('Table %s does not contain a row with %s.', $table, json_encode($values));
        }

        self::assertGreaterThan(0, (int) $connection->fetchOne($sql, $parameters), $message);
    }

    private function getFixtureConnection(): Connection
    {
        if ($this->schemaStrategyConnection === null) {
            self::fail('No fixture connection, call fixtureFromConnection() or fixtureFromNewConnection() first.');
        }

        return $this->schemaStrategyConnection;
    }

    protected function createSchemaStrategy(Connection $connection): SchemaStrategy
    {
        return (new CreateSchemaStrategy())($connection);
    }
}
