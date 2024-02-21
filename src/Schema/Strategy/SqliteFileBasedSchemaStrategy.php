<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema\Strategy;

use ArrayObject;
use Brainbits\FunctionalTestHelpers\Schema\DataBuilder;
use Brainbits\FunctionalTestHelpers\Schema\SchemaBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Identifier;

use function assert;
use function count;
use function dirname;
use function file_exists;
use function is_string;
use function Safe\copy;
use function Safe\mkdir;
use function Safe\unlink;

final class SqliteFileBasedSchemaStrategy implements SchemaStrategy
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

    public function deleteData(Connection $connection): void
    {
    }

    public function resetSequences(Connection $connection): void
    {
    }

    public function applySchema(SchemaBuilder $schemaBuilder, Connection $connection): void
    {
        $params = $connection->getParams();

        $pathToDatabase = $params['path'] ?? null;
        assert(is_string($pathToDatabase));

        $pathToCache = $pathToDatabase . '.cache';
        $pathToDatabaseDirectory = dirname($pathToDatabase);

        $databaseExists = file_exists($pathToDatabase);
        $cacheExists = file_exists($pathToCache);
        $databaseDirectoryExists = file_exists($pathToDatabaseDirectory);

        if (!$databaseDirectoryExists) {
            mkdir($pathToDatabaseDirectory);
        }

        if (!$databaseExists) {
            self::$executedStatements = new ArrayObject();
        }

        if (count(self::$executedStatements) === 0) {
            if ($databaseExists) {
                unlink($pathToDatabase);
                $databaseExists = false;
            }

            if ($cacheExists) {
                unlink($pathToCache);
                $cacheExists = false;
            }
        }

        if ($cacheExists) {
            copy($pathToCache, $pathToDatabase);
        }

        $databaseChanged = $this->applyMissingSchema($schemaBuilder, $connection);

        // phpcs:ignore SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed
        if ($databaseChanged) {
            copy($pathToDatabase, $pathToCache);
        }
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

    private function applyMissingSchema(SchemaBuilder $schemaBuilder, Connection $connection): bool
    {
        $databaseChanged = false;

        foreach ($schemaBuilder->getSchema()->toSql($connection->getDatabasePlatform()) as $sql) {
            if (self::$executedStatements->offsetExists($sql)) {
                continue;
            }

            self::$executedStatements[$sql] = $connection->executeStatement($sql);
            $databaseChanged = true;
        }

        return $databaseChanged;
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
