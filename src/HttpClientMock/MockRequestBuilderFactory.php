<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\UnprocessableBody;
use Riverline\MultiPartParser\StreamedPart;

use function array_key_exists;
use function assert;
use function explode;
use function is_array;
use function is_callable;
use function is_string;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\json_decode;
use function Safe\preg_match;
use function Safe\rewind;
use function strpos;
use function urldecode;

final class MockRequestBuilderFactory
{
    /** @param mixed[] $options */
    public function __invoke(string $method, string $url, array $options): MockRequestBuilder
    {
        $mockRequestBuilder = (new MockRequestBuilder())
            ->method($method)
            ->uri($url);

        foreach ($options['headers'] ?? [] as $header) {
            [$key, $value] = explode(': ', (string) $header);

            $mockRequestBuilder->header((string) $key, (string) $value);
        }

        if (array_key_exists('json', $options)) {
            $mockRequestBuilder->json($options['json']);
        }

        if (array_key_exists('body', $options)) {
            $this->processBody($mockRequestBuilder, $options['body'], $options['headers'] ?? []);
        }

        return $mockRequestBuilder;
    }

    /**
     * @param mixed[]|string|callable|null $body
     * @param mixed[]                      $headers
     */
    private function processBody(
        MockRequestBuilder $mockRequestBuilder,
        array|string|callable|null $body,
        array $headers,
    ): void {
        $contentType = (string) $mockRequestBuilder->getHeader('Content-Type');
        $contentLength = $mockRequestBuilder->getHeader('Content-Length') ?? 0;

        // application/json; charset=utf-8
        if (strpos($contentType, 'application/json') === 0) {
            if (is_string($body)) {
                $mockRequestBuilder->json(json_decode($body, true));
            } elseif (is_callable($body)) {
                $mockRequestBuilder->json(json_decode((string) $body((int) $contentLength), true));
            } elseif (is_array($body)) {
                $mockRequestBuilder->json($body);
            } else {
                throw UnprocessableBody::create();
            }

            return;
        }

        if (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
            assert(is_string($body));

            if (preg_match('/[^=]+=[^=]*(&[^=]+=[^=]*)*/', $body)) {
                foreach (explode('&', $body) as $keyValue) {
                    [$key, $value] = explode('=', $keyValue);

                    $mockRequestBuilder->requestParam(urldecode($key), urldecode($value));
                }

                return;
            }
        }

        // multipart/form-data; charset=utf-8; boundary=__X_PAW_BOUNDARY__
        if (strpos($contentType, 'multipart/form-data') === 0) {
            $stream = fopen('php://temp', 'rw');

            foreach ($headers as $header) {
                fwrite($stream, $header . "\r\n");
            }

            fwrite($stream, "\r\n");

            if (is_string($body)) {
                fwrite($stream, $body);
            } elseif (is_callable($body)) {
                while ($chunk = ($body)(1000)) {
                    fwrite($stream, $chunk);
                }
            } else {
                throw UnprocessableBody::create();
            }

            rewind($stream);

            $mp = new StreamedPart($stream);
            foreach ($mp->getParts() as $part) {
                assert($part instanceof StreamedPart);

                $mockRequestBuilder->multipart(
                    $part->getName(),
                    mimetype: $part->getMimeType(),
                    filename: $part->getFileName(),
                    content: $part->getBody(),
                );
            }

            return;
        }

        if (is_string($body)) {
            $mockRequestBuilder->content($body);

            return;
        }

        if (is_callable($body)) {
            $mockRequestBuilder->content((string) $body());

            return;
        }

        throw UnprocessableBody::create();
    }
}
