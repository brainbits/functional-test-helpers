<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema\Strategy;

use Brainbits\FunctionalTestHelpers\Schema\DataBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Identifier;

final class SqliteMemoryBasedSchemaStrategy implements SchemaStrategy
{
    public function deleteData(Connection $connection): void
    {
    }

    public function resetSequences(Connection $connection): void
    {
    }

    public function applySchema(SchemaBuilder $schemaBuilder, Connection $connection): void
    {
        // a second fixture on the same connection must not create the tables again
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
