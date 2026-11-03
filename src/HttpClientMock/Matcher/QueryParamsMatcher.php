<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function array_is_list;
use function array_map;
use function is_array;
use function is_float;
use function is_int;
use function ksort;
use function Safe\json_encode;
use function sprintf;

/**
 * Matches if the query parameters are exactly the expected ones, extra parameters are a mismatch.
 */
final readonly class QueryParamsMatcher implements Matcher
{
    /** @param array<string, string|mixed[]> $params */
    public function __construct(private array $params)
    {
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch
    {
        $expected = self::flatten($this->params);
        $real = $realRequest->getQueryParams();

        ksort($expected);
        ksort($real);

        if ($expected !== $real) {
            return Mismatch::mismatchingQueryParam('*', $expected, $real);
        }

        return Hit::matchesQueryParam('*', $real);
    }

    /**
     * Nested parameters like ['filter' => ['a' => 1]] are received as "filter[a]", lists as list.
     *
     * @param mixed[] $params
     *
     * @return array<string, string|mixed[]>
     */
    private static function flatten(array $params, string $prefix = ''): array
    {
        $flat = [];
        foreach ($params as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '[' . $key . ']';

            if (is_array($value) && !array_is_list($value)) {
                $flat += self::flatten($value, $name);

                continue;
            }

            $flat[$name] = is_array($value) ? array_map(self::stringify(...), $value) : self::stringify($value);
        }

        return $flat;
    }

    private static function stringify(mixed $value): mixed
    {
        return is_int($value) || is_float($value) ? (string) $value : $value;
    }

    public function __toString(): string
    {
        return sprintf('request.queryParams === %s', json_encode($this->params));
    }
}
