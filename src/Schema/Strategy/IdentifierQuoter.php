<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema\Strategy;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_map;
use function explode;
use function implode;
use function str_replace;

/**
 * Quotes the table and column names a DataBuilder uses the way DBAL's Identifier with forced quoting did,
 * without AbstractAsset::getQuotedName(), which DBAL 4 deprecates (doctrine/dbal#6674): quote characters in
 * the name (`, ", [ and ]) are dropped, and each dot-separated part is quoted for the platform.
 *
 * @internal
 */
final class IdentifierQuoter
{
    public static function quote(AbstractPlatform $platform, string $identifier): string
    {
        $name = str_replace(['`', '"', '[', ']'], '', $identifier);

        return implode('.', array_map($platform->quoteSingleIdentifier(...), explode('.', $name)));
    }
}
