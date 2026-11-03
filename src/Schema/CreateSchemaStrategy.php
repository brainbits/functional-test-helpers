<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema;

use Brainbits\FunctionalTestHelpers\Schema\Strategy\MysqlBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\MysqlDamaBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteFileBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteFileDamaBasedSchemaStrategy;
use Brainbits\FunctionalTestHelpers\Schema\Strategy\SqliteMemoryBasedSchemaStrategy;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Throwable;

use function class_exists;
use function getenv;
use function str_contains;
use function str_ends_with;

final class CreateSchemaStrategy
{
    public function __invoke(Connection $connection): SchemaStrategy
    {
        $forcedStrategy = $this->forcedStrategy();
        if ($forcedStrategy !== null) {
            return $forcedStrategy;
        }

        $params = $connection->getParams();

        if ($this->isSqLiteMemory($params)) {
            return new SqliteMemoryBasedSchemaStrategy();
        }

        if ($this->isSqLiteFile($params)) {
            if ($this->isDamaDoctrineCacheBundleActive($connection->getDriver())) {
                return new SqliteFileDamaBasedSchemaStrategy();
            }

            return new SqliteFileBasedSchemaStrategy();
        }

        if ($this->isMysql($params)) {
            if ($this->isDamaDoctrineCacheBundleActive($connection->getDriver())) {
                return new MysqlDamaBasedSchemaStrategy();
            }

            return new MysqlBasedSchemaStrategy();
        }

        $platformStrategy = $this->strategyFromPlatform($connection, $params);
        if ($platformStrategy !== null) {
            return $platformStrategy;
        }

        throw NoSchemaStrategyFound::forConnectionParameters($params);
    }

    /**
     * SCHEMA_STRATEGY forces a strategy, e.g. if the detection doesn't fit the setup.
     */
    private function forcedStrategy(): SchemaStrategy|null
    {
        $strategy = getenv('SCHEMA_STRATEGY');
        if ($strategy === false || $strategy === '') {
            $strategy = $_SERVER['SCHEMA_STRATEGY'] ?? $_ENV['SCHEMA_STRATEGY'] ?? '';
        }

        return match ((string) $strategy) {
            '' => null,
            'sqlite-memory' => new SqliteMemoryBasedSchemaStrategy(),
            'sqlite-file' => new SqliteFileBasedSchemaStrategy(),
            'sqlite-file-dama' => new SqliteFileDamaBasedSchemaStrategy(),
            'mysql' => new MysqlBasedSchemaStrategy(),
            'mysql-dama' => new MysqlDamaBasedSchemaStrategy(),
            default => throw NoSchemaStrategyFound::unknownStrategy((string) $strategy),
        };
    }

    /**
     * Fallback for drivers that are not recognized by name, e.g. a custom driverClass.
     *
     * @param mixed[] $params
     */
    private function strategyFromPlatform(Connection $connection, array $params): SchemaStrategy|null
    {
        try {
            $platform = $connection->getDatabasePlatform();
        } catch (Throwable) {
            return null;
        }

        if ($platform instanceof SQLitePlatform) {
            $path = (string) ($params['path'] ?? '');

            if ($path === '' || $path === ':memory:' || ($params['memory'] ?? false)) {
                return new SqliteMemoryBasedSchemaStrategy();
            }

            return $this->isDamaDoctrineCacheBundleActive($connection->getDriver())
                ? new SqliteFileDamaBasedSchemaStrategy()
                : new SqliteFileBasedSchemaStrategy();
        }

        if ($platform instanceof AbstractMySQLPlatform) {
            return $this->isDamaDoctrineCacheBundleActive($connection->getDriver())
                ? new MysqlDamaBasedSchemaStrategy()
                : new MysqlBasedSchemaStrategy();
        }

        return null;
    }

    /** @param mixed[] $params */
    private function isSqLiteMemory(array $params): bool
    {
        $url = (string) ($params['url'] ?? '');
        $driver = (string) ($params['driver'] ?? '');
        $memory = (bool) ($params['memory'] ?? false);

        return (str_contains($url, 'sqlite:') && str_contains($url, ':memory:'))
            || ($memory === true && $this->isSqliteDriver($driver));
    }

    /** @param mixed[] $params */
    private function isSqLiteFile(array $params): bool
    {
        $url = (string) ($params['url'] ?? '');
        $driver = (string) ($params['driver'] ?? '');
        $path = (string) ($params['path'] ?? '');

        return $path !== '' && (str_contains($url, 'sqlite:') || $this->isSqliteDriver($driver));
    }

    /** @param mixed[] $params */
    private function isMysql(array $params): bool
    {
        $driver = (string) ($params['driver'] ?? '');

        return str_ends_with($driver, 'mysql') || $driver === 'mysqli';
    }

    private function isSqliteDriver(string $driver): bool
    {
        return str_ends_with($driver, 'sqlite') || $driver === 'sqlite3';
    }

    private function isDamaDoctrineCacheBundleActive(Driver|null $driver): bool
    {
        if (!class_exists(StaticDriver::class)) {
            return false;
        }

        return $driver instanceof StaticDriver && StaticDriver::isKeepStaticConnections();
    }
}
