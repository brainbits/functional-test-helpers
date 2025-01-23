<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\HttpClientMockTrait;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\MethodMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\QueryParamMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\Matcher\UriParams;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;

final class HttpClientMockTraitTest extends TestCase
{
    use HttpClientMockTrait;

    private static MockRequestBuilderCollection|null $collection = null;

    public function setUp(): void
    {
        self::$collection = new MockRequestBuilderCollection();
    }

    public static function getContainer(): Container
    {
        $container = new Container();
        $container->set(MockRequestBuilderCollection::class, self::$collection);

        return $container;
    }

    public function testMockRequest(): void
    {
        $this->mockRequest('GET', '/query')
            ->name('test')
            ->queryParam('firstname', 'peter')
            ->queryParam('lastname', '%s', 'peterson')
            ->queryParam('email', '')
            ->header('content-type', 'text/plain')
            ->content('this is text')
            ->willRespond(
                $this->mockResponse()
                    ->header('content-type', 'text/plain')
                    ->content('test'),
            );

        // simulate http request
        (self::$collection)(
            'GET',
            '/query?firstname=peter&lastname=peterson&email=',
            [
                'headers' => ['Content-Type: text/plain'],
                'body' => 'this is text',
            ],
        );

        $this->assertAllRequestMocksAreCalled();
    }

    public function testMockRequestParsesLegacyQueryParamsFromUri(): void
    {
        $builder = $this->mockRequest('GET', '/query?&firstname=peter&lastname={lastname}&email=');

        $this->assertEquals(
            $builder->getMatcher(),
            new MockRequestMatcher(null, [
                new MethodMatcher('GET'),
                new QueryParamMatcher('firstname', 'peter', []),
                new QueryParamMatcher('lastname', '{lastname}', []),
                new QueryParamMatcher('email', '', []),
                new UriMatcher('/query', new UriParams()),
            ]),
        );
    }

    public function testMockRequestParsesLegacyQueryParamsFromUriWithHost(): void
    {
        $builder = $this->mockRequest('GET', 'https://example.com/query?&firstname=peter&lastname={lastname}&email=');

        $this->assertEquals(
            $builder->getMatcher(),
            new MockRequestMatcher(null, [
                new MethodMatcher('GET'),
                new QueryParamMatcher('firstname', 'peter', []),
                new QueryParamMatcher('lastname', '{lastname}', []),
                new QueryParamMatcher('email', '', []),
                new UriMatcher('https://example.com/query', new UriParams()),
            ]),
        );
    }
}
