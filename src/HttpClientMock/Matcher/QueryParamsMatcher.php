<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;

use function explode;
use function http_build_query;
use function is_array;
use function ksort;
use function rawurldecode;
use function Safe\json_encode;
use function sprintf;

use const PHP_QUERY_RFC3986;
use const SORT_STRING;

/**
 * Matches if the query parameters are exactly the expected ones, extra parameters are a mismatch.
 */
final readonly class QueryParamsMatcher implements Matcher
{
    /** @param mixed[] $params */
    public function __construct(private array $params)
    {
    }

    public function __invoke(RealRequest $realRequest): Hit|Mismatch
    {
        $expected = self::encode($this->params);
        $real = self::indexLists($realRequest->getQueryParams());

        // numeric keys are integers in PHP arrays, only a string sort is a total order for mixed keys
        ksort($expected, SORT_STRING);
        ksort($real, SORT_STRING);

        if ($expected !== $real) {
            return Mismatch::mismatchingQueryParam('*', $expected, $real);
        }

        return Hit::matchesQueryParam('*', $real);
    }

    /**
     * Encodes the expected parameters like Symfony HttpClient's query option does (http_build_query()):
     * nested parameters and lists become "filter[a]" and "ids[0]", booleans 1 and 0, null values are left out.
     *
     * @param mixed[] $params
     *
     * @return array<string, string>
     */
    private static function encode(array $params): array
    {
        $encoded = [];
        foreach (explode('&', http_build_query($params, '', '&', PHP_QUERY_RFC3986)) as $keyValue) {
            if ($keyValue === '') {
                continue;
            }

            [$key, $value] = explode('=', $keyValue, 2) + [1 => ''];
            $encoded[rawurldecode($key)] = rawurldecode($value);
        }

        return $encoded;
    }

    /**
     * Lists received as "ids[]=1&ids[]=2" become "ids[0]" and "ids[1]", like the expected parameters.
     *
     * @param array<string, string|mixed[]> $params
     *
     * @return array<string, string|mixed[]>
     */
    private static function indexLists(array $params): array
    {
        $indexed = [];
        foreach ($params as $key => $value) {
            if (!is_array($value)) {
                $indexed[$key] = $value;

                continue;
            }

            foreach ($value as $index => $item) {
                $indexed[$key . '[' . $index . ']'] = $item;
            }
        }

        return $indexed;
    }

    public function __toString(): string
    {
        return sprintf('request.queryParams === %s', json_encode($this->params));
    }
}
