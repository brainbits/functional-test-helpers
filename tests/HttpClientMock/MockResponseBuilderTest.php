<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\MockResponseBuilder;
use Brainbits\FunctionalTestHelpers\HttpClientMock\RealRequest;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use function implode;
use function microtime;

use const PHP_EOL;

#[CoversClass(MockResponseBuilder::class)]
final class MockResponseBuilderTest extends TestCase
{
    use RealRequestTrait;

    public function testBuildContentResponse(): void
    {
        $builder = (new MockResponseBuilder())
            ->code(202)
            ->contentType('text/plain')
            ->contentLength(12)
            ->etag('plain-content')
            ->content('this is text');

        $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));
        $response = $client->request('GET', '/query');

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('this is text', $response->getContent());
        $headers = $response->getHeaders();
        $this->assertSame(['plain-content'], $headers['etag'] ?? false);
        $this->assertSame(['12'], $headers['content-length'] ?? false);
        $this->assertSame(['text/plain'], $headers['content-type'] ?? false);
    }

    public function testBuildJsonResponse(): void
    {
        $builder = (new MockResponseBuilder())
            ->code(203)
            ->etag('json-content')
            ->json(['foo' => 'bar']);

        $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));
        $response = $client->request('GET', '/query');

        $this->assertSame(203, $response->getStatusCode());
        $this->assertSame('{"foo":"bar"}', $response->getContent());
        $headers = $response->getHeaders();
        $this->assertSame(['json-content'], $headers['etag'] ?? false);
        $this->assertSame(['application/json'], $headers['content-type'] ?? false);
    }

    public function testBuildXmlResponse(): void
    {
        $builder = (new MockResponseBuilder())
            ->code(204)
            ->etag('xml-content')
            ->xml('<foo>bar</foo>');

        $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));
        $response = $client->request('GET', '/query');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('<foo>bar</foo>', $response->getContent());
        $headers = $response->getHeaders();
        $this->assertSame(['xml-content'], $headers['etag'] ?? false);
        $this->assertSame(['text/xml'], $headers['content-type'] ?? false);
    }

    public function testBuildThatResponse(): void
    {
        $builder = (new MockResponseBuilder())
            ->fromCallback(static function (RealRequest $realRequest): MockResponse {
                return new MockResponse(
                    'response from that',
                    [
                        'http_code' => 201,
                        'response_headers' => [
                            'content-type' => 'text/plain',
                            'content-length' => '18',
                        ],
                    ],
                );
            });

        $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));
        $response = $client->request('GET', '/query');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('response from that', $response->getContent());
        $headers = $response->getHeaders();
        $this->assertSame(['text/plain'], $headers['content-type'] ?? false);
        $this->assertSame(['18'], $headers['content-length'] ?? false);
    }

    public function testThatIgnoresOtherValues(): void
    {
        $builder = (new MockResponseBuilder())
            ->fromCallback(static function (RealRequest $realRequest): MockResponse {
                return new MockResponse('response from that', ['http_code' => 201]);
            })
            ->code(200)
            ->contentType('image/gif')
            ->contentLength(6)
            ->etag('foobar')
            ->content('barbaz');

        $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));
        $response = $client->request('GET', '/query');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('response from that', $response->getContent());
        $headers = $response->getHeaders();
        $this->assertFalse($headers['etag'] ?? false);
        $this->assertFalse($headers['content-length'] ?? false);
        $this->assertFalse($headers['content-type'] ?? false);
    }

    public function testConvertableToStringWithJson(): void
    {
        $builder = (new MockResponseBuilder())
            ->code(200)
            ->header('Content-Language', 'de')
            ->json(['json' => 'content']);

        $parts = [
            'HTTP Code: 200',
            'Content-Language: de',
            'Content-Type: application/json',
            '',
            '{"json":"content"}',
        ];

        self::assertSame(implode(PHP_EOL, $parts), (string) $builder);
    }

    public function testConvertableToStringWithXml(): void
    {
        $builder = (new MockResponseBuilder())
            ->code(200)
            ->header('Content-Language', 'de')
            ->xml('<foo/>');

        $parts = [
            'HTTP Code: 200',
            'Content-Language: de',
            'Content-Type: text/xml',
            '',
            '<foo/>',
        ];

        self::assertSame(implode(PHP_EOL, $parts), (string) $builder);
    }

    public function testConvertableToStringWithHeaders(): void
    {
        $builder = (new MockResponseBuilder())
            ->code(200)
            ->contentType('image/gif')
            ->contentLength(6)
            ->etag('foobar')
            ->content('barbaz');

        $parts = [
            'HTTP Code: 200',
            'Content-Type: image/gif',
            'Content-Length: 6',
            'Etag: foobar',
            '',
            'barbaz',
        ];

        self::assertSame(implode(PHP_EOL, $parts), (string) $builder);
    }

    public function testConvertableToStringWithThat(): void
    {
        $builder = (new MockResponseBuilder())
            ->fromCallback(static function (RealRequest $realRequest): MockResponse {
                return new MockResponse('foo');
            })
            ->contentType('image/gif')
            ->contentLength(6)
            ->etag('foobar')
            ->content('barbaz');

        self::assertSame('callable(realRequest)', (string) $builder);
    }

    public function testTransportErrorFailsWhenTheResponseIsRead(): void
    {
        $builder = (new MockResponseBuilder())->transportError('connection refused');

        $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));
        $response = $client->request('GET', 'http://localhost/');

        $message = null;
        try {
            $response->getContent();
        } catch (TransportException $e) {
            $message = $e->getMessage();
        }

        // the exception trace references the response; release both so no error chunk is left behind
        unset($e, $response, $client);

        $this->assertSame('connection refused', $message);
    }

    public function testTimeoutFailsWhenTheResponseIsRead(): void
    {
        $builder = (new MockResponseBuilder())->timeout();

        $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));
        $response = $client->request('GET', 'http://localhost/');

        $exceptionClass = null;
        try {
            $response->getContent();
        } catch (TimeoutException $e) {
            $exceptionClass = $e::class;
        }

        // the exception trace references the response; release both so no error chunk is left behind
        unset($e, $response, $client);

        $this->assertSame(TimeoutException::class, $exceptionClass);
    }

    public function testChunkedContent(): void
    {
        $builder = (new MockResponseBuilder())->chunks(['a', 'b', 'c']);

        $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));
        $response = $client->request('GET', 'http://localhost/');

        $chunks = [];
        foreach ($client->stream($response) as $chunk) {
            if ($chunk->getContent() === '') {
                continue;
            }

            $chunks[] = $chunk->getContent();
        }

        $this->assertSame(['a', 'b', 'c'], $chunks);
    }

    public function testChunksFromAGeneratorCanBeReturnedRepeatedly(): void
    {
        $generator = (static function (): Generator {
            yield 'a';
            yield 'b';
        })();

        $builder = (new MockResponseBuilder())->chunks($generator);

        foreach ([1, 2] as $run) {
            $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));

            $this->assertSame('ab', $client->request('GET', 'http://localhost/')->getContent(), 'run ' . $run);
        }
    }

    public function testDelay(): void
    {
        $builder = (new MockResponseBuilder())->content('late')->delay(0.05);

        $client = new MockHttpClient($builder->getResponse($this->createRealRequest()));

        $start = microtime(true);
        $content = $client->request('GET', 'http://localhost/')->getContent();

        $this->assertSame('late', $content);
        $this->assertGreaterThanOrEqual(0.04, microtime(true) - $start);
    }

    public function testFailureToString(): void
    {
        $this->assertSame('Transport error: boom', (string) (new MockResponseBuilder())->transportError('boom'));
        $this->assertSame('Timeout', (string) (new MockResponseBuilder())->timeout());
    }
}
