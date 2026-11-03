<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock\Matcher;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\Hit;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\QueryParamsMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequestFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
}
