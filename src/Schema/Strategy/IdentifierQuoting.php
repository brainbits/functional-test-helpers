<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema\Strategy;

use Doctrine\DBAL\Platforms\AbstractPlatform;

use function array_map;
use function explode;
use function implode;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

/** @internal */
final class IdentifierQuoting
{
    /**
     * Quotes each part of a possibly qualified name (schema.table) with the quote character of the platform.
     * Parts that are already quoted with `, " or [] are re-quoted, like Identifier::getQuotedName() did.
     */
    public static function quote(AbstractPlatform $platform, string $identifier): string
    {
        return implode('.', array_map(
            static fn (string $part): string => $platform->quoteSingleIdentifier(
                self::isQuoted($part) ? substr($part, 1, -1) : $part,
            ),
            explode('.', $identifier),
        ));
    }

    private static function isQuoted(string $part): bool
    {
        if (strlen($part) < 2) {
            return false;
        }

        foreach (['`' => '`', '"' => '"', '[' => ']'] as $open => $close) {
            if (str_starts_with($part, $open) && str_ends_with($part, $close)) {
                return true;
            }
        }

        return false;
    }
}
