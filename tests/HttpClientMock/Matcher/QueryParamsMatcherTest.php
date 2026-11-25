<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\QueryParamsMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(QueryParamsMatcher::class)]
final class QueryParamsMatcherTest extends TestCase
{
    /** @return iterable<string, array{mixed[], string, bool}> */
    public static function provideParams(): iterable
    {
        yield 'exact' => [['page' => '1', 'q' => 'x'], 'q=x&page=1', true];
        yield 'extra parameter' => [['page' => '1'], 'page=1&q=x', false];
        yield 'integer value' => [['page' => 1], 'page=1', true];
        yield 'nested' => [['filter' => ['a' => '1', 'b' => '2']], 'filter%5Ba%5D=1&filter%5Bb%5D=2', true];
        yield 'list' => [['ids' => ['1', '2']], 'ids%5B%5D=1&ids%5B%5D=2', true];
        yield 'numeric and other keys' => [['1e' => 'c', '10' => 'b', '2' => 'a'], '1e=c&10=b&2=a', true];
        yield 'numeric keys in other order' => [['1e' => 'c', '10' => 'b', '2' => 'a'], '10=b&1e=c&2=a', true];
        yield 'nested list' => [
            ['filter' => ['ids' => [1, 2]]],
            'filter%5Bids%5D%5B%5D=1&filter%5Bids%5D%5B%5D=2',
            true,
        ];
    }

    /** @param mixed[] $params */
    #[DataProvider('provideParams')]
    public function testMatch(array $params, string $query, bool $matches): void
    {
        $realRequest = (new RealRequestFactory())('GET', 'https://api.test/?' . $query, []);

        self::assertSame($matches, (new QueryParamsMatcher($params))($realRequest) instanceof Hit);
    }

    /** @return iterable<string, array{mixed[]}> */
    public static function provideSymfonyQueries(): iterable
    {
        yield 'scalars' => [['page' => 1, 'q' => 'x y']];
        yield 'list' => [['ids' => [1, 2]]];
        yield 'nested list' => [['filter' => ['ids' => [1, 2], 'name' => 'a']]];
        yield 'booleans' => [['debug' => true, 'verbose' => false]];
        yield 'null' => [['page' => 1, 'cursor' => null]];
    }

    /** @param mixed[] $query */
    #[DataProvider('provideSymfonyQueries')]
    public function testMatchesTheQueryOptionOfSymfonyHttpClient(array $query): void
    {
        $realRequest = null;
        $client = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$realRequest): MockResponse {
                $realRequest = (new RealRequestFactory())($method, $url, $options);

                return new MockResponse();
            },
        );
        $client->request('GET', 'https://api.test/users', ['query' => $query]);

        self::assertNotNull($realRequest);
        self::assertInstanceOf(Hit::class, (new QueryParamsMatcher($query))($realRequest));
    }
}
