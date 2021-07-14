<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

use function trigger_error;

use const E_USER_DEPRECATED;

// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

/**
 * @mixin TestCase
 */
trait SchemaTrait
{
    /**
     * @deprecated use fixtureFromConnection()
     */
    final protected function fixtureFromServiceConnection(
        Connection $connection,
        SchemaBuilder $schemaBuilder,
        DataBuilder $dataBuilder,
        callable $buildData
    ): Connection {
        trigger_error(
            'fixtureFromServiceConnection() is deprecated, use fixtureFromConnection()',
            E_USER_DEPRECATED,
        );

        $this->fixtureFromConnection($connection, $schemaBuilder, $dataBuilder, $buildData);

        return $connection;
    }

    final protected function fixtureFromConnection(
        Connection $connection,
        SchemaBuilder $schemaBuilder,
        DataBuilder $dataBuilder,
        callable $buildData
    ): void {
        $buildData($dataBuilder);

        $this->applySchema($schemaBuilder, $connection);
        $this->applyData($dataBuilder, $connection);
    }

    final protected function fixtureFromNewConnection(
        SchemaBuilder $schemaBuilder,
        DataBuilder $dataBuilder,
        callable $buildData
    ): Connection {
        $connection = DriverManager::getConnection(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ],
            null,
            new EventManager(),
        );

        $this->fixtureFromConnection($connection, $schemaBuilder, $dataBuilder, $buildData);

        return $connection;
    }

    /**
     * @internal
     */
    private function applySchema(SchemaBuilder $schemaBuilder, Connection $connection): void
    {
        foreach ($schemaBuilder->getSchema()->toSql($connection->getDatabasePlatform()) as $sql) {
            $connection->exec($sql);
        }
    }

    /**
     * @internal
     */
    private function applyData(DataBuilder $dataBuilder, Connection $connection): void
    {
        foreach ($dataBuilder->getData() as $table => $rows) {
            foreach ($rows as $row) {
                $connection->insert($connection->quoteIdentifier($table), $row);
            }
        }
    }
}
