<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema\Strategy;

use ArrayObject;
use Brainbits\FunctionalTestHelpers\Schema\DataBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Identifier;

use function count;
use function sprintf;

final class MysqlBasedSchemaStrategy implements SchemaStrategy
{
    /** @var ArrayObject<string, int|string> */
    public static ArrayObject $executedStatements;

    public function __construct(bool $resetExecutedStatements = false)
    {
        if ($resetExecutedStatements) {
            self::$executedStatements = new ArrayObject();
        }

        self::$executedStatements ??= new ArrayObject();
    }

    public function applySchema(SchemaBuilder $schemaBuilder, Connection $connection): void
    {
        $platform = $connection->getDatabasePlatform();

        $existingTables = $connection->executeQuery($platform->getListTablesSQL())->fetchFirstColumn();

        if (count($existingTables) > 0) {
            if (count(self::$executedStatements) === 0) {
                $this->dropTables($existingTables, $connection);

                self::$executedStatements = new ArrayObject();
            }
        }

        foreach ($schemaBuilder->getSchema()->toSql($platform) as $sql) {
            if (self::$executedStatements->offsetExists($sql)) {
                continue;
            }

            self::$executedStatements[$sql] = $connection->executeStatement($sql);
        }
    }

    public function deleteData(Connection $connection): void
    {
        $tablesWithRows = $connection->executeQuery(
            'SELECT `TABLE_NAME` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :table',
            ['table' => $connection->getDatabase()],
        )->fetchFirstColumn();

        if (!$tablesWithRows) {
            return;
        }

        try {
            $connection->executeStatement('SET foreign_key_checks = 0');

            foreach ($tablesWithRows as $table) {
                $connection->executeStatement(
                    sprintf('DELETE FROM %s', $connection->quoteIdentifier($table)),
                );
            }
        } finally {
            $connection->executeStatement('SET foreign_key_checks = 1');
        }
    }

    public function resetSequences(Connection $connection): void
    {
        $tablesWithAutoIncrements = $connection->executeQuery(
            'SELECT `TABLE_NAME` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :table AND `AUTO_INCREMENT` > 1',
            ['table' => $connection->getDatabase()],
        )->fetchFirstColumn();

        foreach ($tablesWithAutoIncrements as $table) {
            $connection->executeStatement(
                sprintf('ALTER TABLE %s AUTO_INCREMENT = 1', $connection->quoteIdentifier($table)),
            );
        }
    }

    public function applyData(DataBuilder $dataBuilder, Connection $connection): void
    {
        try {
            $connection->executeStatement('SET foreign_key_checks = 0');

            foreach ($dataBuilder->getData() as $table => $rows) {
                $table = $this->quoteIdentifier($connection, $table);

                foreach ($rows as $row) {
                    $row = $this->quoteKeys($connection, $row);

                    $connection->insert($table, $row);
                }
            }
        } finally {
            $connection->executeStatement('SET foreign_key_checks = 1');
        }
    }

    /** @param list<string> $tables */
    private function dropTables(array $tables, Connection $connection): void
    {
        $platform = $connection->getDatabasePlatform();

        try {
            $connection->executeStatement('SET foreign_key_checks = 0');

            foreach ($tables as $table) {
                $listForeignKeysSql = $platform->getListTableForeignKeysSQL($table);
                $foreignKeys = $connection->executeQuery($listForeignKeysSql)->fetchFirstColumn();

                foreach ($foreignKeys as $foreignKey) {
                    $dropForeignKeySQL = $platform->getDropForeignKeySQL($foreignKey, $table);
                    $connection->executeStatement($dropForeignKeySQL);
                }

                $dropTableSQL = $platform->getDropTableSQL($table);
                $connection->executeStatement($dropTableSQL);
            }
        } finally {
            $connection->executeStatement('SET foreign_key_checks = 1');
        }
    }

    private function quoteIdentifier(Connection $connection, string $identifier): string
    {
        return (new Identifier($identifier, true))->getQuotedName($connection->getDatabasePlatform());
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     *
     * @interal
     */
    private function quoteKeys(Connection $connection, mixed $row): array
    {
        $quotedRow = [];

        foreach ($row as $key => $value) {
            $quotedRow[$this->quoteIdentifier($connection, $key)] = $value;
        }

        return $quotedRow;
    }
}
