<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema;

use RuntimeException;

use function Safe\json_encode;
use function sprintf;

final class NoSchemaStrategyFound extends RuntimeException
{
    /** @param mixed[] $connectionParameters */
    public static function forConnectionParameters(array $connectionParameters): self
    {
        throw new self(
            sprintf(
                'No apply schema strategy found for connection parameters: %s',
                json_encode($connectionParameters),
            ),
        );
    }
}
