<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema\Strategy;

use Brainbits\FunctionalTestHelpers\Schema\DataBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Doctrine\DBAL\Connection;

use function assert;
use function dirname;
use function file_exists;
use function is_string;
use function Safe\mkdir;

final class SqliteFileDamaBasedSchemaStrategy implements SchemaStrategy
{
    public function deleteData(Connection $connection): void
    {
        // data is deleted by DAMA DoctrineTestBundle
    }

    public function resetSequences(Connection $connection): void
    {
    }

    public function applySchema(SchemaBuilder $schemaBuilder, Connection $connection): void
    {
        $params = $connection->getParams();

        $pathToDatabase = $params['path'] ?? null;
        assert(is_string($pathToDatabase));

        $pathToDatabaseDirectory = dirname($pathToDatabase);

        $databaseDirectoryExists = file_exists($pathToDatabaseDirectory);

        if (!$databaseDirectoryExists) {
            mkdir($pathToDatabaseDirectory, 0777, true);
        }

        // the file may already contain the tables, e.g. after a run without DAMA or a #[SkipDatabaseRollback] test
        SqliteTables::createMissing($schemaBuilder, $connection);
    }

    public function applyData(DataBuilder $dataBuilder, Connection $connection): void
    {
        foreach ($dataBuilder->getData() as $table => $rows) {
            $table = $this->quoteIdentifier($connection, $table);

            foreach ($rows as $row) {
                $row = $this->quoteKeys($connection, $row);

                $connection->insert($table, $row);
            }
        }
    }

    private function quoteIdentifier(Connection $connection, string $identifier): string
    {
        return IdentifierQuoting::quote($connection->getDatabasePlatform(), $identifier);
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
