<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema\Strategy;

use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Doctrine\DBAL\Connection;

use function array_flip;
use function array_key_exists;
use function array_map;
use function strtolower;

/** @internal */
final class SqliteTables
{
    /**
     * Creates the tables of the schema that don't exist yet. Existing tables are kept as they are.
     */
    public static function createMissing(SchemaBuilder $schemaBuilder, Connection $connection): void
    {
        $platform = $connection->getDatabasePlatform();

        $existingTables = array_flip(array_map(
            static fn (mixed $name): string => strtolower((string) $name),
            $connection->fetchFirstColumn("SELECT name FROM sqlite_master WHERE type = 'table'"),
        ));

        foreach ($schemaBuilder->getSchema()->getTables() as $table) {
            if (array_key_exists(strtolower($table->getName()), $existingTables)) {
                continue;
            }

            foreach ($platform->getCreateTableSQL($table) as $sql) {
                $connection->executeStatement($sql);
            }
        }
    }
}
