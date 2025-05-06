<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema\Strategy;

use ArrayObject;
use Brainbits\FunctionalTestHelpers\Schema\DataBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Identifier;

use function count;
use function sprintf;

final class MysqlDamaBasedSchemaStrategy implements SchemaStrategy
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

        $existingTables = $connection->createSchemaManager()->listTableNames();

        StaticDriver::rollBack();

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

        StaticDriver::beginTransaction();
    }

    public function deleteData(Connection $connection): void
    {
        // data is deleted by DAMA DoctrineTestBundle
    }

    public function resetSequences(Connection $connection): void
    {
        $tablesWithAutoIncrements = $connection->executeQuery(
            'SELECT `TABLE_NAME` FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :table AND `AUTO_INCREMENT` > 1',
            ['table' => $connection->getDatabase()],
        )->fetchFirstColumn();

        if (!$tablesWithAutoIncrements) {
            return;
        }

        StaticDriver::rollBack();

        foreach ($tablesWithAutoIncrements as $table) {
            $connection->executeStatement(
                sprintf('ALTER TABLE %s AUTO_INCREMENT = 1', $connection->quoteIdentifier($table)),
            );
        }

        StaticDriver::beginTransaction();
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

    /** @param list<string> $tableNames */
    private function dropTables(array $tableNames, Connection $connection): void
    {
        $schemaManager = $connection->createSchemaManager();

        try {
            $connection->executeStatement('SET foreign_key_checks = 0');

            foreach ($tableNames as $tableName) {
                foreach ($schemaManager->listTableForeignKeys($tableName) as $foreignKey) {
                    $schemaManager->dropForeignKey($foreignKey->getName(), $tableName);
                }

                $schemaManager->dropTable($tableName);
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
