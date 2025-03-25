<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use Brainbits\FunctionalTestHelpers\HttpClientMock\Exception\UnprocessableBody;
use Riverline\MultiPartParser\StreamedPart;

use function array_key_exists;
use function array_merge;
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
use function str_contains;
use function str_ends_with;
use function str_replace;
use function strpos;
use function strtolower;
use function substr;
use function urldecode;

final class RealRequestFactory
{
    /** @param mixed[] $options */
    public function __invoke(string $method, string $url, array $options): RealRequest
    {
        $headers = [];
        $content = null;
        $json = null;
        $queryParams = $options['query_params'] ?? [];
        $requestParams = [];
        $multiparts = [];

        $queryParamStart = strpos($url, '?');
        if ($queryParamStart !== false) {
            $encodedParams = substr($url, $queryParamStart + 1);
            $url = substr($url, 0, $queryParamStart);

            foreach ($this->parseEncodedParams($encodedParams) as $key => $value) {
                $queryParams[$key] = $value;
            }
        }

        foreach ($options['headers'] ?? [] as $header) {
            [$key, $value] = explode(': ', (string) $header);

            $headers[strtolower($key)] = $value;
        }

        if (array_key_exists('json', $options)) {
            $json = $options['json'];
        }

        if (array_key_exists('body', $options)) {
            $body = $options['body'];
            $contentType = $headers['content-type'] ?? '';
            $contentLength = $headers['content-length'] ?? 0;

            // application/json; charset=utf-8
            if (strpos($contentType, 'application/json') === 0) {
                if (is_string($body)) {
                    $json = json_decode($body, true);
                } elseif (is_callable($body)) {
                    $json = json_decode((string) $body((int) $contentLength), true);
                } elseif (is_array($body)) {
                    $json = $body;
                } else {
                    throw UnprocessableBody::create();
                }
            }

            if (strpos($contentType, 'application/x-www-form-urlencoded') === 0) {
                assert(is_string($body));

                if (preg_match('/[^=]+=[^=]*(&[^=]+=[^=]*)*/', $body)) {
                    foreach (explode('&', $body) as $keyValue) {
                        [$key, $value] = explode('=', $keyValue);

                        $requestParams[urldecode($key)] = urldecode($value);
                    }
                }
            }

            // multipart/form-data; charset=utf-8; boundary=__X_PAW_BOUNDARY__
            if (strpos($contentType, 'multipart/form-data') === 0) {
                $stream = fopen('php://temp', 'rw');

                foreach ($headers as $key => $value) {
                    fwrite($stream, $key . ': ' . $value . "\r\n");
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

                    $multiparts[$part->getName()] = [
                        'name' => $part->getName(),
                        'mimetype' => $part->getMimeType(),
                        'filename' => $part->getFileName(),
                        'content' => $part->getBody(),
                    ];
                }
            }

            if (is_string($body)) {
                $content = $body;
            } elseif (is_callable($body)) {
                $content = (string) $body((int) $contentLength);
            }
        }

        return new RealRequest(
            $method,
            $url,
            $headers,
            $content,
            $json,
            $queryParams,
            $requestParams,
            $multiparts,
        );
    }

    /** @return string[] */
    private function parseEncodedParams(string $encodedParams): array
    {
        if ($encodedParams === '') {
            return [];
        }

        $params = [];

        foreach (explode('&', $encodedParams) as $keyValue) {
            if (str_contains($keyValue, '=')) {
                [$key, $value] = explode('=', (string) $keyValue);
            } else {
                $key = $keyValue;
                $value = '';
            }

            $key = urldecode($key);
            $value = urldecode($value);

            if (str_ends_with($key, '[]')) {
                $key = str_replace('[]', '', $key);
                $value = array_merge(
                    $params[$key] ?? [],
                    [$value],
                );
            }

            $params[$key] = $value;
        }

        return $params;
    }
}
