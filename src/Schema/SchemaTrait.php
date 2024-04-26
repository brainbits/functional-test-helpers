<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

use function getenv;

// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter

/** @mixin TestCase */
trait SchemaTrait
{
    final protected function fixtureFromConnection( // @phpstan-ignore-line
        Connection $connection,
        SchemaBuilder $schemaBuilder,
        DataBuilder $dataBuilder,
        callable $buildData,
    ): void {
        $buildData($dataBuilder);

        $schemaStrategy = (new CreateSchemaStrategy())($connection);

        if (!getenv('USE_PRE_INITIALIZED_SCHEMA')) {
            $schemaStrategy->applySchema($schemaBuilder, $connection);
        }

        $schemaStrategy->deleteData($connection);
        $schemaStrategy->resetSequences($connection);
        $schemaStrategy->applyData($dataBuilder, $connection);
    }

    final protected function fixtureFromNewConnection( // @phpstan-ignore-line
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
}
