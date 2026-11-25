<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Snapshot;

use function array_key_exists;
use function array_pop;
use function count;
use function is_string;
use function json_decode;
use function strlen;
use function substr;

/**
 * Finds keys that occur more than once in the same JSON object. json_decode() silently keeps the last one,
 * so a snapshot comparison would ignore the others.
 */
final class DuplicateJsonKey
{
    /** Returns the first duplicate key, or null if every object has unique keys. */
    public static function find(string $json): string|null
    {
        /** @var list<array<string, true>|null> $stack null for an array, the keys seen so far for an object */
        $stack = [];
        $expectKey = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($char === '{') {
                $stack[] = [];
                $expectKey = true;
            } elseif ($char === '[') {
                $stack[] = null;
                $expectKey = false;
            } elseif ($char === '}' || $char === ']') {
                array_pop($stack);
                $expectKey = false;
            } elseif ($char === ',') {
                $expectKey = count($stack) > 0 && $stack[count($stack) - 1] !== null;
            } elseif ($char === '"') {
                $start = $i;
                for ($i++; $i < $length && $json[$i] !== '"'; $i++) {
                    if ($json[$i] !== '\\') {
                        continue;
                    }

                    $i++;
                }

                if (!$expectKey) {
                    continue;
                }

                $key = json_decode(substr($json, $start, $i - $start + 1));
                $key = is_string($key) ? $key : substr($json, $start + 1, $i - $start - 1);
                $keys = &$stack[count($stack) - 1];

                if (array_key_exists($key, $keys)) {
                    return $key;
                }

                $keys[$key] = true;
                unset($keys);
                $expectKey = false;
            }
        }

        return null;
    }
}
