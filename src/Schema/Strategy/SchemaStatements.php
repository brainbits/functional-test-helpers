<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Schema\Strategy;

use function array_key_exists;
use function array_keys;
use function Safe\preg_match;

/**
 * Helps the strategies that cache executed schema statements across tests.
 *
 * @internal
 */
final class SchemaStatements
{
    /**
     * True if a table of the new statements was already created with a different definition,
     * e.g. because another test class defines the same table with more columns.
     *
     * @param array<string, mixed> $executedStatements executed SQL statements as keys
     * @param list<string>         $newStatements
     */
    public static function hasConflictingTables(array $executedStatements, array $newStatements): bool
    {
        $createdTables = [];
        foreach (array_keys($executedStatements) as $statement) {
            $table = self::createdTable($statement);
            if ($table === null) {
                continue;
            }

            $createdTables[$table] = true;
        }

        foreach ($newStatements as $statement) {
            $table = self::createdTable($statement);

            if ($table === null || !($createdTables[$table] ?? false)) {
                continue;
            }

            if (!array_key_exists($statement, $executedStatements)) {
                return true;
            }
        }

        return false;
    }

    private static function createdTable(string $statement): string|null
    {
        $matches = [];
        if (!preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?([^\s(]+)/i', $statement, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
