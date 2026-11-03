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
use function is_iterable;
use function is_resource;
use function is_string;
use function max;
use function Safe\fopen;
use function Safe\fwrite;
use function Safe\json_decode;
use function Safe\preg_match;
use function Safe\rewind;
use function Safe\stream_get_contents;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
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
            [$key, $value] = explode(':', (string) $header, 2) + [1 => ''];
            $key = strtolower(trim($key));
            $value = trim($value);

            // repeated headers keep the last value, combining them is planned for 8.0 (#138)
            $headers[$key] = $value;
        }

        if (array_key_exists('json', $options)) {
            $json = $options['json'];
        }

        if (array_key_exists('body', $options)) {
            $body = $options['body'];
            $contentType = $headers['content-type'] ?? '';
            $contentLength = (int) ($headers['content-length'] ?? 0);

            // read streamed bodies only once, they can't be rewound
            if (!is_string($body) && !is_array($body)) {
                $body = $this->readBody($body, $contentLength);
            }

            // application/json; charset=utf-8
            if (strpos($contentType, 'application/json') === 0) {
                if (is_string($body)) {
                    $json = $this->decodeJson($body);
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
                        if (!str_contains($keyValue, '=')) {
                            $requestParams[urldecode($keyValue)] = '';

                            continue;
                        }

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

                if (!is_string($body)) {
                    throw UnprocessableBody::create();
                }

                fwrite($stream, $body);

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

    private function readBody(mixed $body, int $contentLength): string
    {
        if (is_resource($body)) {
            return stream_get_contents($body);
        }

        if (is_iterable($body)) {
            $content = '';
            foreach ($body as $chunk) {
                $content .= $chunk;
            }

            return $content;
        }

        if (!is_callable($body)) {
            throw UnprocessableBody::create();
        }

        $content = '';
        while (($chunk = (string) $body(max($contentLength, 16372))) !== '') {
            $content .= $chunk;

            if ($contentLength > 0 && strlen($content) >= $contentLength) {
                break;
            }
        }

        return $content;
    }

    /** @return mixed[]|null */
    private function decodeJson(string $content): array|null
    {
        if ($content === '') {
            return null;
        }

        $json = json_decode($content, true);

        // scalar JSON documents can only be matched via content()
        return is_array($json) ? $json : null;
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
                    (array) ($params[$key] ?? []),
                    [$value],
                );
            }

            $params[$key] = $value;
        }

        return $params;
    }
}
