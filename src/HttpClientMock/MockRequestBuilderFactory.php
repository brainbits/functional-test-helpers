<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\UnprocessableBody;
use Riverline\MultiPartParser\StreamedPart;

use function array_key_exists;
use function assert;
use function explode;
use function is_callable;
use function is_string;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\json_decode;
use function Safe\preg_match;
use function Safe\rewind;
use function strlen;
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
     * @param mixed[]|string|null $body
     * @param mixed[]             $headers
     */
    private function processBody(MockRequestBuilder $mockRequestBuilder, array|string|null $body, array $headers): void
    {
        $contentType = (string) $mockRequestBuilder->getHeader('Content-Type');

        // application/json; charset=utf-8
        if (strpos($contentType, 'application/json') === 0) {
            $mockRequestBuilder->json(json_decode($body, true));

            return;
        }

        if (
            strpos($contentType, 'application/x-www-form-urlencoded') === 0
            && preg_match('/[^=]+=[^=]*(&[^=]+=[^=]*)*/', (string) $body)
        ) {
            foreach (explode('&', $body) as $keyValue) {
                [$key, $value] = explode('=', $keyValue);

                $mockRequestBuilder->requestParam(urldecode($key), urldecode($value));
            }

            return;
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
                $mockRequestBuilder->multipartFile(
                    $part->getName(),
                    $part->getFileName(),
                    $part->getMimeType(),
                    strlen($part->getBody()),
                );
            }

            return;
        }

        $mockRequestBuilder->content($body);
    }
}
