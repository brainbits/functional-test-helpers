<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function array_is_list;
use function array_key_exists;
use function is_array;
use function Safe\json_encode;
use function sprintf;

/**
 * Matches if the JSON body contains the expected keys and values. Additional keys are ignored,
 * lists have to match exactly.
 */
final readonly class JsonSubsetMatcher implements Matcher
{
    /** @param mixed[] $subset */
    public function __construct(private array $subset)
    {
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch
    {
        $realJson = $realRequest->getJson();

        if ($realJson === null || !self::contains($this->subset, $realJson)) {
            return Mismatch::mismatchingJson(
                json_encode($this->subset),
                $realJson !== null ? json_encode($realJson) : null,
            );
        }

        return Hit::matchesJson(json_encode($realJson));
    }

    /**
     * @param mixed[] $subset
     * @param mixed[] $actual
     */
    private static function contains(array $subset, array $actual, bool $nested = false): bool
    {
        // lists (and a nested empty array) have to match exactly, including the types of the values
        if (array_is_list($subset) && ($subset !== [] || $nested)) {
            return $subset === $actual;
        }

        foreach ($subset as $key => $value) {
            if (!array_key_exists($key, $actual)) {
                return false;
            }

            if (is_array($value) && is_array($actual[$key])) {
                if (!self::contains($value, $actual[$key], true)) {
                    return false;
                }

                continue;
            }

            if ($value !== $actual[$key]) {
                return false;
            }
        }

        return true;
    }

    public function __toString(): string
    {
        return sprintf('request.json contains "%s"', json_encode($this->subset));
    }
}
