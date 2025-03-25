<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\HttpClientMock;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function http_build_query;
use function Safe\array_combine;
use function Safe\json_encode;
use function sprintf;
use function strtolower;
use function trim;

use const PHP_EOL;

final class RealRequest
{
    /**
     * @param array<string, string>                                                                      $headers
     * @param mixed[]                                                                                    $json
     * @param array<string, string|mixed[]>                                                              $queryParams
     * @param array<string, string>                                                                      $requestParams
     * @param array<string, array{name: string, filename?: string, mimetype?: string, content?: string}> $multiparts
     */
    public function __construct(
        private string $method,
        private string $uri,
        private array $headers,
        private string|null $content,
        private array|null $json,
        private array $queryParams,
        private array $requestParams,
        private array $multiparts,
    ) {
        $this->headers = array_combine(
            array_map(strtolower(...), array_keys($headers)),
            array_values($headers),
        );
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function hasHeader(string $key): bool
    {
        return array_key_exists($key, $this->headers);
    }

    public function getHeader(string $key): string|null
    {
        return $this->headers[strtolower($key)] ?? null;
    }

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getContent(): string|null
    {
        return $this->content;
    }

    /** @return mixed[]|null */
    public function getJson(): array|null
    {
        return $this->json;
    }

    public function hasQueryParam(string $key): bool
    {
        return array_key_exists($key, $this->queryParams);
    }

    /** @return string|mixed[]|null */
    public function getQueryParam(string $key): string|array|null
    {
        return $this->queryParams[$key] ?? null;
    }

    /** @return array<string, string> */
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function hasRequestParam(string $key): bool
    {
        return array_key_exists($key, $this->requestParams);
    }

    public function getRequestParam(string $key): string|null
    {
        return $this->requestParams[$key] ?? null;
    }

    /** @return array<string, string> */
    public function getRequestParams(): array
    {
        return $this->requestParams;
    }

    public function hasMultipart(string $name): bool
    {
        return (bool) ($this->multiparts[$name] ?? false);
    }

    /** @return array{name: string, filename?: string, mimetype?: string, content?: string}|null */
    public function getMultipart(string $name): array|null
    {
        return $this->multiparts[$name] ?? null;
    }

    /** @return array<string, array{name: string, filename?: string, mimetype?: string, content?: string}> */
    public function getMultiparts(): array
    {
        return $this->multiparts;
    }

    public function __toString(): string
    {
        $string = $this->method . ' ' . $this->uri;

        if ($this->queryParams) {
            $string .= '?' . http_build_query($this->queryParams);
        }

        if ($this->headers) {
            $string .= PHP_EOL;
            foreach ($this->headers as $key => $header) {
                $string .= sprintf('%s: %s%s', $key, $header, PHP_EOL);
            }
        }

        if ($this->requestParams) {
            foreach ($this->requestParams as $key => $value) {
                $string .= sprintf('&%s=%s', $key, $value);
            }

            $string .= PHP_EOL;
        } elseif ($this->multiparts) {
            foreach ($this->multiparts as $key => $multipart) {
                $parts = ($multipart['filename'] ?? false ? sprintf(', filename=%s', $multipart['filename']) : '') .
                    ($multipart['mimetype'] ?? false ? sprintf(', mimetype=%s', $multipart['mimetype']) : '') .
                    ($multipart['content'] ?? false ? sprintf(', content=%s', $multipart['content']) : '');
                $string .= sprintf('%s: name=%s, %s%s', $key, $multipart['name'], $parts, PHP_EOL);
            }
        } elseif ($this->json !== null) {
            $string .= json_encode($this->json);
        } elseif ($this->content !== null) {
            $string .= $this->content;
        }

        return trim($string);
    }
}
