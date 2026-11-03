<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema;

use RuntimeException;

use function array_key_exists;
use function Safe\json_encode;
use function sprintf;

final class NoSchemaStrategyFound extends RuntimeException
{
    /** @param mixed[] $connectionParameters */
    public static function forConnectionParameters(array $connectionParameters): self
    {
        foreach (['password', 'url', 'primary', 'replica', 'driverOptions'] as $sensitiveKey) {
            if (!array_key_exists($sensitiveKey, $connectionParameters)) {
                continue;
            }

            $connectionParameters[$sensitiveKey] = '***';
        }

        return new self(
            sprintf(
                'No apply schema strategy found for connection parameters: %s',
                json_encode($connectionParameters),
            ),
        );
    }

    public static function unknownStrategy(string $strategy): self
    {
        return new self(sprintf(
            'Unknown schema strategy "%s" in SCHEMA_STRATEGY, use one of: %s',
            $strategy,
            'sqlite-memory, sqlite-file, sqlite-file-dama, mysql, mysql-dama',
        ));
    }
}
