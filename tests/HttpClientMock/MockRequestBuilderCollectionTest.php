<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestBuilderCollection;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockRequestMatcher;
use Brainbits\FunctionalTestHelpers\HttpClientMock\MockResponseBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MockRequestBuilder::class)]
#[CoversClass(MockRequestBuilderCollection::class)]
#[CoversClass(MockRequestMatcher::class)]
final class MockRequestBuilderCollectionTest extends TestCase
{
    private MockRequestBuilderCollection $collection;
    /** @var MockRequestBuilder[] */
    private array $builders = [];

    public function setUp(): void
    {
        $this->builders = [
            'fallback' => (new MockRequestBuilder())
                ->name('fallback')
                ->willRespond(new MockResponseBuilder()),

            'get' => (new MockRequestBuilder())
                ->name('get')
                ->method('GET')
                ->willRespond(new MockResponseBuilder()),

            'post' => (new MockRequestBuilder())
                ->name('post')
                ->method('POST')
                ->willRespond(new MockResponseBuilder()),

            'get_uri_header' => (new MockRequestBuilder())
                ->name('header')
                ->method('GET')
                ->uri('/uri')
                ->header('Accept', 'application/zip')
                ->willRespond(new MockResponseBuilder()),

            'only_uri' => (new MockRequestBuilder())
                ->name('uri')
                ->uri('/only-uri')
                ->willRespond(new MockResponseBuilder()),

            'get_uri' => (new MockRequestBuilder())
                ->name('get_uri')
                ->method('GET')
                ->uri('/uri')
                ->willRespond(new MockResponseBuilder()),

            'get_uri_param' => (new MockRequestBuilder())
                ->name('get_uri_param')
                ->method('GET')
                ->uri('/uri')
                ->queryParam('one', '1')
                ->willRespond(new MockResponseBuilder()),

            'get_uri_params' => (new MockRequestBuilder())
                ->name('get_uri_params')
                ->method('GET')
                ->uri('/uri')
                ->queryParam('one', '1')
                ->queryParam('two', '2')
                ->willRespond(new MockResponseBuilder()),

            'post_uri_json' => (new MockRequestBuilder())
                ->name('post_uri_json')
                ->method('POST')
                ->uri('/uri')
                ->json(['json' => 'data'])
                ->willRespond(new MockResponseBuilder()),

            'post_uri_xml' => (new MockRequestBuilder())
                ->name('post_uri_xml')
                ->method('POST')
                ->uri('/uri')
                ->xml('<name>test</name>')
                ->willRespond(new MockResponseBuilder()),

            'post_uri_param' => (new MockRequestBuilder())
                ->name('post_uri_param')
                ->method('POST')
                ->uri('/uri')
                ->requestParam('one', '1')
                ->willRespond(new MockResponseBuilder()),

            'post_uri_params' => (new MockRequestBuilder())
                ->name('post_uri_params')
                ->method('POST')
                ->uri('/uri')
                ->requestParam('one', '1')
                ->requestParam('two', '2')
                ->willRespond(new MockResponseBuilder()),

            'post_uri_content' => (new MockRequestBuilder())
                ->name('post_uri_content')
                ->method('POST')
                ->uri('/uri')
                ->content('content')
                ->willRespond(new MockResponseBuilder()),

            'post_uri_multipart' => (new MockRequestBuilder())
                ->name('post_uri_multipart')
                ->method('POST')
                ->uri('/uri')
                ->multipart('key', 'application/octet-stream', null, 'content')
                ->willRespond(new MockResponseBuilder()),
        ];

        $this->collection = new MockRequestBuilderCollection();
        foreach ($this->builders as $builder) {
            $this->collection->addMockRequestBuilder($builder);
        }
    }

    /** @param mixed[] $options */
    #[DataProvider('requests')]
    public function testRequestMatching(string $method, string $uri, array $options, string $index): void
    {
        $x = ($this->collection)($method, $uri, $options);

        $expectedMockRequestBuilder = $this->builders[$index];

        self::assertFalse($expectedMockRequestBuilder->getCallStack()->isEmpty());
    }

    public function testOnMatch(): void
    {
        $called = false;

        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder(
            (new MockRequestBuilder())
                ->method('GET')
                ->uri('/query')
                ->onMatch(static function () use (&$called): void {
                    $called = true;
                })
                ->willRespond(new MockResponseBuilder()),
        );

        $collection('GET', '/query', []);

        self::assertTrue($called, 'onMatch() was not called');
    }

    public function testAssertContent(): void
    {
        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder(
            (new MockRequestBuilder())
                ->assertContent(function (string $content): void {
                    $this->assertSame('this is content', $content);
                })
                ->willRespond(new MockResponseBuilder()),
        );

        $collection('GET', '/query', ['body' => 'this is content']);
    }

    public function testAssertContentFails(): void
    {
        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder(
            (new MockRequestBuilder())
                ->assertContent(function (string $content): void {
                    $this->assertSame('this is content', $content);
                })
                ->willRespond(new MockResponseBuilder()),
        );

        try {
            $collection('GET', '/query', ['body' => 'does-not-match']);
        } catch (AssertionFailedError) {
            return;
        }

        $this->fail('Expected assertion was not thrown');
    }

    public function testAssertThat(): void
    {
        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder(
            (new MockRequestBuilder())
                ->assertThat(function (RealRequest $realRequest): void {
                    $this->assertSame('this is content', $realRequest->getContent());
                })
                ->willRespond(new MockResponseBuilder()),
        );

        $collection('GET', '/query', ['body' => 'this is content']);
    }

    public function testAssertThatFails(): void
    {
        $collection = new MockRequestBuilderCollection();
        $collection->addMockRequestBuilder(
            (new MockRequestBuilder())
                ->assertThat(function (RealRequest $realRequest): void {
                    $this->assertSame('this is content', $realRequest->getContent());
                })
                ->willRespond(new MockResponseBuilder()),
        );

        try {
            $collection('GET', '/query', ['body' => 'does-not-match']);
        } catch (AssertionFailedError) {
            return;
        }

        $this->fail('Expected assertion was not thrown');
    }

    /** @return mixed[] */
    public static function requests(): array
    {
        return [
            'delete' => ['DELETE', '/not-matched', [], 'fallback'],
            'get' => ['GET', '/not-matched', [], 'get'],
            'post' => ['POST', '/not-matched', [], 'post'],
            'getUriHeader' => ['GET', '/uri', ['headers' => ['Accept: application/zip']], 'get_uri_header'],
            'getOnlyUri' => ['GET', '/only-uri', [], 'only_uri'],
            'postOnlyUri' => ['POST', '/only-uri', [], 'only_uri'],
            'deleteOnlyUri' => ['DELETE', '/only-uri', [], 'only_uri'],
            'getUri' => ['GET', '/uri', [], 'get_uri'],
            'getUriWithOneParam' => ['GET', '/uri?one=1', [], 'get_uri_param'],
            'getUriWithTwoParams' => ['GET', '/uri?one=1&two=2', [], 'get_uri_params'],
            'postUri' => ['POST', '/uri', [], 'post'],
            'postUriJson' => ['POST', '/uri', ['json' => ['json' => 'data']], 'post_uri_json'],
            'postUriXml' => [
                'POST',
                '/uri',
                ['body' => '<name>test</name>', 'headers' => ['Content-Type: text/xml']],
                'post_uri_xml',
            ],
            'postUriWithOneParam' => [
                'POST',
                '/uri',
                ['body' => 'one=1', 'headers' => ['Content-Type: application/x-www-form-urlencoded']],
                'post_uri_param',
            ],
            'postUriWithTwoParams' => [
                'POST',
                '/uri',
                ['body' => 'one=1&two=2', 'headers' => ['Content-Type: application/x-www-form-urlencoded']],
                'post_uri_params',
            ],
            'postUriWithContent' => [
                'POST',
                '/uri',
                ['body' => 'content', 'headers' => ['Content-Type: application/x-www-form-urlencoded']],
                'post_uri_content',
            ],
            'postUriWithMultipart' => [
                'POST',
                '/uri',
                [
                    'body' => <<<'BODY'
                    --12345
                    Content-Disposition: form-data; name="key"
                    
                    content
                    --12345--
                    BODY,
                    'headers' => ['Content-Type: multipart/form-data; boundary=12345'],
                ],
                'post_uri_multipart',
            ],
        ];
    }
}
