<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema;

use Brainbits\FunctionalTestHelpers\Schema\Strategy\SchemaStrategy;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

use function assert;
use function getenv;

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
        assert($this->schemaStrategyConnection !== null);

        $this->schemaStrategy?->deleteData($this->schemaStrategyConnection);
        $this->schemaStrategy?->resetSequences($this->schemaStrategyConnection);

        self::$isSchemaDatabaseClean = true;
    }

    protected function createSchemaStrategy(Connection $connection): SchemaStrategy
    {
        return (new CreateSchemaStrategy())($connection);
    }
}
